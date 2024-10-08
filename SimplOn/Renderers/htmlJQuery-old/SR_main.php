<?php

use voku\helper\HtmlDomParser;

class SR_main-old extends SC_BaseObject{

    protected
        $SimplOn_path,
        $App_path,
        $App_web_root,

        $cssWebRoot,
        $jsWebRoot,
        $imgsWebRoot,

        $URL_METHOD_SEPARATOR ='!',
        $WEB_ROOT;

    static
        $layoutsCache,
        $outputtemplate,
        $csslinks,
        $jslinks = array();


    function addMethod($name, $method)
    {
        $this->{$name} = $method;
    }

    public function App_web_root($App_web_root)
    {
        if($App_web_root){
            $this->App_web_root =   $App_web_root;
            $this->cssWebRoot =   $App_web_root.'/Layouts/css';
            $this->jsWebRoot =    $App_web_root.'/Layouts/js';
            $this->imgsWebRoot =  $App_web_root.'/Layouts/imgs';
        }else{
            return $this->App_web_root;
        }
    }


    function renderData( $Data, $method, $template = null, $messages = null, $action=null,$nextStep=null){
        $specialRendererPath = $this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.DIRECTORY_SEPARATOR.'specialRenderers'.DIRECTORY_SEPARATOR.'SR_'.$Data->getClass().'.php';

        //Fill the template
        if(!file_exists($specialRendererPath)){ //normal 

            if(!$template OR SC_Main::$Layouts_Processing=='OverWrite' OR SC_Main::$Layouts_Processing=='OnTheFly'){
                $template = $this->getDataLayout($Data, $method); 
            }          
            $ret = $this->fillDataDomWithVariables($Data, $template);
        }else{      //Special

            require_once($specialRendererPath);

            $className = get_class($Data);
            $SR_special_Get = $className . '_SR_special_Get';
            $SR_special_Check = $className . '_SR_special_Check';

            $specialCheck = $SR_special_Check($Data, $template, $method);
   
            if (!$template OR SC_Main::$Layouts_Processing === 'OverWrite' OR SC_Main::$Layouts_Processing=='OnTheFly') {
                $template = $SR_special_Get($Data, $method); 
            } elseif ($specialCheck !== 'Ok') {
                //TODO: Doing the same that above review what really ned to be done to consider the template and Update 
                $template = $SR_special_Get($Data, $method); 
            }
            $SR_special_Fill = $Data->getClass().'_SR_special_Fill';

            $ret = $SR_special_Fill($Data, $template);
        }
        
        return $ret->find('section')->first()->innerHTML();
    }    

    function render( $object, $method, $output = 'AE_fullPage', $template = null, $action=null,$nextStep=null,$noCancelButton=false){

        //Clean the Sysmessage so it's not added to the URLs
       $SystemMessage = SC_Main::$SystemMessage;
        SC_Main::$SystemMessage='';   
        
       // get (or make) the template
       if(!$template){ $template = $this->getElementLayout($object, $method, $noCancelButton); }        
        //Fill the template
        
       $template = $this->fillDatasInElementLayout($object,$template,$method);
       $template = $this->fillVariablesInElementLayout($object,$template,$action);


        if($output){
            $output = new $output();
            $output->message($SystemMessage);
            $output->content($template->html());
            $outputtemplate = $this->directlayoutPath($output, 'showView');
            $outputtemplate = QP::withHTML5(file_get_contents($outputtemplate));
            $this->getJSandCSS($outputtemplate);
            $outputtemplate = $this->addCSSandJSLinksToTemplate($outputtemplate);

            return $this->fillDatasInElementLayout($output,$outputtemplate,'showView');
        }else{
            //if the message has not been printed reset it
            SC_Main::$SystemMessage=$SystemMessage;
            return $template->html();
        }

    }

    function addCSSandJSLinksToTemplate($dom){
    if($dom->find('head')->html() != ''){              
            $dom->find("head link[rel='stylesheet']")->remove();

            foreach(self::$csslinks as $csslink){

                if(substr($csslink, 0, 4) == 'http' && substr($csslink, -4) == '.css'){
                    $dom->find('head')->append('<link rel="stylesheet" href="'.$csslink.'"  //>');
                }else{
                    $dom->find('head')->append('<link rel="stylesheet" href="'.$this->cssWebRoot.'/'.basename($csslink).'" //>');
                }
            }
            $dom->find('head script')->remove();
            foreach(self::$jslinks as $jslink){
                if(substr($jslink, 0, 4) == 'http' && substr($jslink, -3) == '.js'){
                    $dom->find('head')->append('<script type="text/javascript" src="'.$jslink.'"> </script>'."\n");
                }else{
                    $dom->find('head')->append('<script type="text/javascript" src="'.$this->jsWebRoot.'/'.basename($jslink).'" /> </script>'."\n");  //NOTE :: space in -" /> </script>- weirdly required
                }
            }
        }
        return $dom;
    }

    function requiredText(SC_BaseObject $object){
        if($object->required()){ return 'required'; }else{ return ''; }
    }

    /* methods related with fix or generate specific HTML parts */
    function VCSLForPeople($object = null, string $VCSL=''){ 
        if(SC_Main::$VCRSL[$VCSL]){ return SC_Main::$VCRSL[$VCSL];}else{ return $VCSL;}
    }

    /* methods related with fix or generate specific HTML parts */
    function BackURL(){ 
        if(isset($_SERVER["HTTP_REFERER"])){
            $url = explode('!!',$_SERVER["HTTP_REFERER"]);
            return $url[0];
        }
    }

    function setOutputDom(string $output,QueryPath\DOMQuery  $content){

        if($output != 'partOnly' && !self::$mainDom){
            $appFile = $this->App_path.DIRECTORY_SEPARATOR.'Layouts'.DIRECTORY_SEPARATOR.$output.'.html';
            $simplonFile = $this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.DIRECTORY_SEPARATOR.$output.'.html';
            if(file_exists($appFile)){ 
                //self::$mainDom = \phpQuery::newDocumentFileHTML($appFile);
                self::$mainDom = QP::withHTML5(file_get_contents($appFile));
            }
            elseif(file_exists($simplonFile)){ 
                //self::$mainDom = \phpQuery::newDocumentFileHTML($simplonFile); 
                self::$mainDom = QP::withHTML5(file_get_contents($simplonFile));
            }

        }elseif($output == 'partOnly'){
            if(!self::$mainDom){
                throw new SR_RendererException('There is not MainDom, there most be one element rendering to a full page to include the CSS and JS');
            } 
        }elseif(!self::$mainDom){
            throw new SR_RendererException('There is con only be one MainDom / Element Reendering to a full page');
        }
      
    }

    /* methods related with the JS and CSS */
    function getJSandCSS(QueryPath\DOMQuery  $dom){
        $this->getStylesAndScriptsLinks($dom);
        //TODO get other scripts and Styles???
    }
        
        function getStylesAndScriptsLinks(voku\helper\HtmlDomParser $dom){ 
            // get head the CSS Links
            foreach ($dom->find('head link[rel="stylesheet"]') as $domLink) {
                // Get the href attribute of each link
                $link = $domLink->href;
                if(substr($link, 0, 4) == 'http' && substr($link, -4) == '.css'){ self::$csslinks[$link]=$link; }
                elseif(substr($link, -4) == '.css'){self::$csslinks[basename($link)]=$link;}
            }

            // get head the JS Links
            foreach ($dom->find('head script') as $domLink) {
               $link = $domLink->src;
               if(substr($link, 0, 4) == 'http' && substr($link, -3) == '.js'){ self::$jslinks[$link]=$link; }
               elseif(substr($link, -3) == '.js'){self::$jslinks[basename($link)]=$link;}
            }
        }

        function addStylesTagsToAutoCSS(SC_BaseObject $data, voku\helper\HtmlDomParser $methodDom, string $method){ 
            global $cssTagsContent;
            
            // get the style tags of the method           
            $cssTagsContent[$data->getClass()]=array(
                'method'=>$method,
                'style'=>$methodDom->find("style", 0)->plaintext
            );
            
            $minifyed = minify_css($cssTagsContent[$data->getClass()]['style']);
            $minifyedWithMarks = "/* START_".$data->getClass()." */\n $minifyed \n/* END_".$data->getClass()." */";
            
            $file = $this->App_path.DIRECTORY_SEPARATOR.'Layouts'.DIRECTORY_SEPARATOR.'css'.DIRECTORY_SEPARATOR.'simplon-auto.css';
            $currentStylesFile = file_get_contents($file);

            $regEx = '/(\/\* START_'.$data->getClass().' \*\/)(\\n.*\\n)(\/\* END_'.$data->getClass().' \*\/)/';
            
            //Put in $currentStile[2] whats now in the simplon-auto.css file 
            preg_match($regEx, $currentStylesFile, $currentStile);
            if(
                array_key_exists(2, $currentStile) 
                && 
                !empty(trim($minifyed))
                &&
                (  trim($currentStile[2]) != trim($minifyed)  ) 
            ){
                //$StylesForFile = preg_replace($regEx, $minifyedWithMarks, $currentStylesFile);
                $StylesForFile = str_replace($currentStile[2], "\n".$minifyed."\n", $currentStylesFile); 
                if(!empty($currentStile[2]) AND $StylesForFile){
                   file_put_contents($file, trim($StylesForFile));
                }
            }elseif(!array_key_exists(2, $currentStile)  && !empty(trim($minifyed))){      
                $regEx = '/\/\* START_AD_Mesa \*\/\s*(.+?)\s*\/\* END_AD_Mesa \*\//s';
                preg_match($regEx, $currentStylesFile, $currentStile);
                if(array_key_exists(0, $currentStile)){
                    $StylesForFile = str_replace($currentStile[0], $minifyedWithMarks."\n", $currentStylesFile); 
                    file_put_contents($file, trim($StylesForFile));
                }else{
                    file_put_contents($file, trim($currentStylesFile)."\n".trim($minifyedWithMarks));
                }              
            }elseif(empty(trim($minifyed))){
                $StylesForFile = preg_replace($regEx, '', $currentStylesFile);
                file_put_contents($file, trim($StylesForFile));
            }
        }   



    function getElementLayout($object, $method, $noCancelButton=false){
        
        $layoutName = $object->getClass().'_'.$method;
        if(!isset(self::$layoutsCache[$layoutName])){
            $directlayoutPath = $this->directlayoutPath($object);
            if(file_exists($directlayoutPath)){
            $check = $this->checkMethodLayout($object, $directlayoutPath, $method);
                if(is_array($check)){ 
                    $changes = $check; 
                    $check = 'Outdated'; 
                } 
            }
    
            if($object instanceof SE_Interface AND file_exists($directlayoutPath)){// if it's Interface ignore the OverWrite and use showView
                $dom = $this->createMethodLayout($object,'showView', null, $noCancelButton);
            }elseif( !file_exists($directlayoutPath) OR (SC_Main::$Layouts_Processing =='OnTheFly') ){
   
                // If there is no file create  the template
                $dom = $this->createMethodLayout($object,$method, null,$noCancelButton);
                $dom = "<section class='".$object->getClass()." $method'>". $dom->html()."</section>";

                if( SC_Main::$Layouts_Processing !='OnTheFly'){
                    $this->writeLayoutFile($dom,$directlayoutPath);
                }
            }elseif( file_exists($directlayoutPath) && $check == 'None' ){ 
                // If there is template file but it has no section for the given method
                $dom = $this->createMethodLayout($object,$method, null, $noCancelButton);
                $dom = "<section class='".$object->getClass()." $method'>". $dom->html()."</section>";
                $this->appendMethodLayout($dom,$directlayoutPath);
            }elseif( file_exists($directlayoutPath) && $check == 'Empty' ){ 

                // If there is template file but it has no section for the given method
                $dom = $this->createMethodLayout($object,$method, null, $noCancelButton);
                $dom = "<section class='".$object->getClass()." $method'>". $dom->html()."</section>";
                $this->updateLayoutFile($dom,$method, $directlayoutPath);
            }elseif( SC_Main::$Layouts_Processing == 'OverWrite' ){ 
                // If the section has to be overwriten
                if($check == 'Ok-NotVCRSL'){
                    //Preserve de existiong NotVCRSL template
                    //$dom = \phpQuery::newDocumentFileHTML();
                    $dom = QP::withHTML5(file_get_contents($directlayoutPath));
                    $this->getStylesAndScriptsLinks($dom);
                    $dom = $dom->find('.'.$method);
                }else{
                    // Overwrite if VCRSL
                    $dom = $this->createMethodLayout($object,$method, null, $noCancelButton);
                    $dom = "<section class='".$object->getClass()." $method'>". $dom->html()."</section>";
                    $this->updateLayoutFile($dom,$method,$directlayoutPath);
                }
            }elseif( SC_Main::$Layouts_Processing == 'Update' AND !str_starts_with($check,'Ok') ){  
                // If Update and not in syc with Element or there is no section to render the method create the section
                $dom = $this->updateMethodLayout($object,$method,$changes, null, $noCancelButton);
                $dom = "<section class='".$object->getClass()." $method'>". $dom->html()."</section>";
                $this->updateLayoutFile($dom,$method, $directlayoutPath);
            }elseif(  
                SC_Main::$Layouts_Processing == 'Preserve' 
                OR 
                (SC_Main::$Layouts_Processing == 'Update' AND str_starts_with($check,'Ok') ) 
                ){

                // if Preserve or template in sync, render the Element with it.
               //----- $dom = \phpQuery::newDocumentFileHTML();
                $dom = QP::withHTML5(file_get_contents($directlayoutPath));
                $this->getStylesAndScriptsLinks($dom);
                $dom = $dom['.'.$method];

            }
            //Returning the same $dom creates unusual flows I think there is trigger or something that alters the dom if the file is altered
            if($dom instanceof QueryPath\DOMQuery ){ 
               //----- $dom = $dom->html(); 
               $dom = $dom->outerHtml(); 
            }

            //----self::$layoutsCache[$layoutName] = \phpQuery::newDocumentHTML($dom); // save a copy to avoid overwring
            self::$layoutsCache[$layoutName] = QP::withHTML5($dom);

            return QP::withHTML5($dom); // returns a copy to avoid overwring
        }else{ 
            $dom = self::$layoutsCache[$layoutName];
            return QP::withHTML5($dom->find('.'.$method)->html()); // returns a copy to avoid overwring
        }
    }

        function directlayoutPath($object){
            if(gettype($object) == 'object' && is_a($object,'SC_BaseObject')){

                if(is_a($object,'SD_Data')){ $dataPath=DIRECTORY_SEPARATOR.'Datas'; }
                if(is_a($object,'SID_Data')){ $dataPath=DIRECTORY_SEPARATOR.'InterfaceDatas'; }
                if(is_a($object,'SC_Element')){ $dataPath=''; }
                if(file_exists($this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.$dataPath.DIRECTORY_SEPARATOR.$object->getClass().'.html')){
                    $ret = $this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.$dataPath.DIRECTORY_SEPARATOR.$object->getClass().'.html';
                }else{
                    $ret = $this->App_path.DIRECTORY_SEPARATOR.'Layouts'.$dataPath.DIRECTORY_SEPARATOR.$object->getClass().'.html';
                }

            }elseif(gettype($object) == 'object' && !is_a($object,'SC_BaseObject')){  
                throw new SR_RendererException('This function can only get the path for Simplon  Datas and Elements');
            }elseif(is_string($object)){
                $ret = $this->App_path.DIRECTORY_SEPARATOR.'Layouts'.DIRECTORY_SEPARATOR.$object.'.html';
                if(!file_exists($ret)){
                    $ret = null;
                }
            }

            return $ret;
        }

        function layoutPath($object){
            if(gettype($object) == 'object' && is_a($object,'SC_BaseObject')){

                //$ret = $this->App_path.DIRECTORY_SEPARATOR.'Layouts'.DIRECTORY_SEPARATOR.$object->getClass().'.html';

                if(is_a($object,'SD_Data')){ $dataPath=DIRECTORY_SEPARATOR.'Datas'; }

                if(is_a($object,'SID_Data') OR is_a($object,'SID_ComplexData')){ $dataPath=DIRECTORY_SEPARATOR.'InterfaceDatas'; }

                if(is_a($object,'SC_Element')){ $dataPath=''; }

                $ancestors = class_parents($object);
                array_splice($ancestors, -1);
                $ancestorClass = $object->getClass();
                while(
                        $ancestorClass 
                        && !file_exists($this->App_path.DIRECTORY_SEPARATOR.'Layouts'.$dataPath.DIRECTORY_SEPARATOR.$ancestorClass.'.html')
                        && !file_exists($this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.$dataPath.DIRECTORY_SEPARATOR.$ancestorClass.'.html')
                    ){
                    $ancestorClass = array_shift($ancestors);
                }
               
                if(file_exists($this->App_path.DIRECTORY_SEPARATOR.'Layouts'.$dataPath.DIRECTORY_SEPARATOR.$ancestorClass.'.html')){
                    $ret = $this->App_path.DIRECTORY_SEPARATOR.'Layouts'.$dataPath.DIRECTORY_SEPARATOR.$ancestorClass.'.html';
                }elseif(file_exists($this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.$dataPath.DIRECTORY_SEPARATOR.$ancestorClass.'.html')){
                    $ret = $this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.$dataPath.DIRECTORY_SEPARATOR.$ancestorClass.'.html';
                }
            }elseif(gettype($object) == 'object' && !is_a($object,'SC_BaseObject')){  
                throw new SR_RendererException('This function can only get the path for Simplon  Datas and Elements');
            }elseif(is_string($object)){
                $ret = $this->App_path.DIRECTORY_SEPARATOR.'Layouts'.DIRECTORY_SEPARATOR.$object.'.html';
                if(!file_exists($ret)){
                    $ret = $this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.$dataPath.DIRECTORY_SEPARATOR.$object.'.html';
                }
                if(!file_exists($ret)){
                    throw new SR_RendererException('Tehere is no template for '.$object);
                }
            }

            return $ret;
        }

        function checkMethodLayout(SC_Element $object, $dom, $method){
            if (is_string($dom)) {$dom = QP::withHTML5($dom);}
            $methodNode = $dom->find('.'.$method);
            $showType = '';
            if(substr($method, 0 ,4) =='show'){$showType=strtolower(substr($method,4));}
            $objectDatasForMethod = $object->datasWith($showType);
      
            if (!$methodNode->outerHtml()) {
                return 'None';
            }elseif(!trim($methodNode->html())){ 
                return 'Empty';
            }elseif(!in_array(strtolower($showType), SC_Main::$VCRSLMethods)){
                return 'Ok-NotVCRSL';
            }elseif($methodNode->hasClass('direct')){
                return 'Ok-Direct';
            }else{
                $datasInLayout = preg_match_all_callback(
                    '/EA_[a-zA-Z0-9]+/',
                    $methodNode->html(),
                    function ($match){
                        return explode('_',$match[0])[1];
                    }
                );

                $ret = array();

                $ret['addToTemplate'] = array_diff(array_unique($objectDatasForMethod), array_unique((array)$datasInLayout));
                $ret['removeFromTemplate'] = array_diff(array_unique((array)$datasInLayout), array_unique($objectDatasForMethod));

                //-- Check for Correct Clasess
                foreach($objectDatasForMethod as $DataName){
                    $DataClass = $object->{'O'.$DataName}()->getClass();
                    if(empty($methodNode['.'.$DataClass.'.EA_'.$DataName])){
                        $ret['removeFromTemplate'][] = $DataName;
                        $ret['addToTemplate'][] = $DataName;
                    }
                }
                $ret['addToTemplate'] = array_unique($ret['addToTemplate']);
                $ret['removeFromTemplate'] = array_unique($ret['removeFromTemplate']);


                if(empty($ret['addToTemplate']) and empty($ret['removeFromTemplate'])){
                    return 'Ok';
                }else{
                    return $ret;
                }
         
            }
        } 

        function updateMethodLayout(SC_Element $object,$method,$changes,$action=null,$noCancelButton=false){ 
            $dom = QP::withHTML5(file_get_contents($this->directlayoutPath($object)));

            $methodNode = $dom[".$method"];

            foreach($changes['removeFromTemplate'] as $dataToRemove){
                $methodNode['.EA_'.$dataToRemove]->remove();
            }

            $objectDatasForMethod = $changes['addToTemplate'];

            if(is_array($objectDatasForMethod)){
                foreach($objectDatasForMethod as  $i=>$objectData) {
                    $dataTemplate = $this->getDataLayout($object->{'O'.$objectData}(),$method); //This has to be here to add the CSS and JS of all datas to the new updated template
            
                    if($i==0){
                        if( stripos($methodNode->html(),'<legend') ){
                            $methodNode["legend"]->after($dataTemplate);
                        }elseif(stripos($methodNode->html(),'<fieldset')){
                            $methodNode["fieldset"]->prepend($dataTemplate);
                        }elseif(stripos($methodNode->html(),'<form')){
                            $methodNode["form"]->prepend($dataTemplate);
                        }else{
                            $methodNode->prepend($dataTemplate);
                        }
                    }else{
                        if(isset($objectDatasForMethod[$i-1])){
                            $methodNode['.EA_'.$objectDatasForMethod[$i-1]]->after($dataTemplate);
                        }
                    }
                    
                }
                $html = $methodNode->html();
                $formTags = array('<input','<select','<textarea','<button','<fieldset', '<legend','<datalist','<output','<option','<optgroup'); 
                if( $this->contains($html,$formTags) && !stripos($html,'<form')) {
                    $enctype='';//todo change enctype if there is file type data
                    $VCSL=substr($method, 4);
    
                    if(SC_Main::$VCRSL[$VCSL]){$VCSLForPeople = SC_Main::$VCRSL[$VCSL];}else{$VCSLForPeople = $VCSL;};
                    if(empty($action)){$action='process'.$VCSL;}
                                
                    $html = '<form class="'.$object->htmlClasses($VCSL).' '.strtolower($VCSL) .'" '
                    . ' action="$action"'
                    . ' method="post" '
                    . @$enctype
                    . '><fieldset><legend>' . $VCSLForPeople . ' ' . $object->Name() . '</legend>'
                    . $html
                    . '<div class="buttons"><button type="submit">' . $VCSLForPeople . '</button>'
                    . (($noCancelButton) ? '<button onclick="location.href =\'$SRBackURL\';" class="SimplOn cancel-form">$SRVCSLForPeople_Cancel </button>' : '')
                    . '</div></fieldset></form>';
                    $methodNode->html($html);
                }
                return $methodNode;
            }elseif($objectDatasForMethod == 'NotVCRSL'){  
                $ret = $this->lookForMethodInElementsTree($object,$method);
                $ret['style']->html('');
            }
        }


        function createMethodLayout(SC_Element $object,$method,$action=null,$noCancelButton=false){ 

            $layoutName = $object->getClass().'_'.$method;
            if(!isset(self::$layoutsCache[$layoutName])){
                $showType = '';
                if(substr($method, 0 ,4) =='show'){$showType=strtolower(substr($method,4));}
                $DatasForMethod = $object->datasWith($showType);
                $ret='';
                if(is_array($DatasForMethod) && $DatasForMethod != 'NotVCRSL'){
                    foreach($DatasForMethod as $Data){
                        $ret .= $this->getDataLayout($object->{'O'.$Data}(),$method)->html();
                    }
        
                    //$ret = \phpQuery::newDocumentHTML($ret);
                    $ret = QP::withHTML5($ret);

                    //$this->getStylesAndScriptsLinks($ret);
                    $html=$ret->html();
                    $enctype='';//todo change enctype if there is file type data
                    $formTags = array('<input','<select','<textarea','<button','<fieldset', '<legend','<datalist','<output','<option','<optgroup'); 
                    $VCSL=substr($method, 4);
            
                    if(SC_Main::$VCRSL[$VCSL]){$VCSLForPeople = SC_Main::$VCRSL[$VCSL];}else{$VCSLForPeople = $VCSL;};
                    if(empty($action)){$action='process'.$VCSL;}
                    if( $this->contains($html,$formTags) ) { 
                        
                        $html = '<form class="'.$object->htmlClasses($VCSL).' '.strtolower($VCSL) .'" '
                        . ' action="$action"'
                        . ' method="post" '
                        . @$enctype
                        . '><fieldset><legend>' . $VCSLForPeople . ' ' . $object->Name() . '</legend>'
                        . $html
                        . '<div class="buttons"><button type="submit">' . $VCSLForPeople . '</button>'
                        . ((!$noCancelButton) ? '<button onclick="location.href =\'$SRBackURL\';" class="SimplOn cancel-form"> $SRVCSLForPeople_Cancel</button>' : '')
                        . '</div></div></fieldset></form>';
                        $ret->html($html);
                    }
                }elseif($DatasForMethod == 'NotVCRSL'){  
                    $ret = $this->lookForMethodInElementsTree($object,$method);
                    $ret['style']->html('');
                }
                
                self::$layoutsCache[$layoutName] = QP::withHTML($ret);
                return QP::withHTML($ret); // returns a copy to avoid overwring
            }else{ 

                $ret = self::$layoutsCache[$layoutName];
                return QP::withHTML(QP($ret, ".$method")->html());
            }
        }

        function getDataLayout(SD_Data $Data, string $method){ 

            $specialRendererPath = $this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.DIRECTORY_SEPARATOR.'specialRenderers'.DIRECTORY_SEPARATOR.'SR_'.$Data->getClass().'.php';

            if(file_exists($specialRendererPath)){
                require_once($specialRendererPath);
                $dom = ($Data->getClass().'_SR_special_Get')($Data, $method);
                $this->getStylesAndScriptsLinks($dom);
                //$this->addStylesTagsToAutoCSS($Data,$dom,$method);
                return $dom;
            }else{
                if($Data->fixedValue() && in_array($method,$Data->parent()::$formMethods) ){
                    $method = 'showFixedValue';
                }                

                $dom = HtmlDomParser::file_get_html($this->layoutPath($Data));
                $fc = $dom->find(".$method > *", 0);
                $fc->class = $fc->class.' '.$Data->getClass().' '.'EA_' . $Data->name();

                $this->getStylesAndScriptsLinks($dom);

                $this->addStylesTagsToAutoCSS($Data,$dom,$method);
                return $dom;
            }
        }

        function contains($str, array $arr){
            foreach($arr as $a) {
                if (stripos($str,$a) !== false) return true;
            }
            return false;
        }


        function appendMethodLayout($domOrfileContent, $pathOrObject){

            if($pathOrObject instanceof SC_Element){
                $filePath = $this->layoutPath($pathOrObject);
            }elseif(is_string($pathOrObject)){
                $filePath = $pathOrObject;
            }

            //$dom = \phpQuery::newDocumentFileHTML($filePath);
            $dom = QP::withHTML5(file_get_contents($filePath));

            if(is_string($domOrfileContent)){
                $newContent = QP::withHTML5(file_get_contents($domOrfileContent));
            }elseif($domOrfileContent instanceof QueryPath\DOMQuery ){
                $newContent = QP::withHTML5($domOrfileContent);
            }

            $dom->find('body')->append($newContent);

            // Get The CSS and JS of the base Tamplate
            $this->getStylesAndScriptsLinks($dom);

            // Add all the CSS and JS (from Datas -previously collected- and the base template)
           if($dom["head"]->html()!=''){              
                QP($dom, "head link[rel='stylesheet']")->remove();
    
                foreach(self::$csslinks as $csslink){

                    if(substr($csslink, 0, 4) == 'http' && substr($csslink, -4) == '.css'){
                        $dom->find('head')->append('<link rel="stylesheet" href="'.$csslink.'"  //>');
                    }else{
                        $dom->find('head')->append('<link rel="stylesheet" href="'.$this->cssWebRoot.'/'.basename($csslink).'" //>');
                    }
                }
                $dom->find('head script')->remove();


                foreach(self::$jslinks as $jslink){
                    if(substr($jslink, 0, 4) == 'http' && substr($jslink, -3) == '.js'){
                        $dom->find('head')->append('<script type="text/javascript" src="'.$jslink.'"> </script>'."\n");
                    }else{
                        $dom->find('head')->append('<script type="text/javascript" src="'.$this->jsWebRoot.'/'.basename($jslink).'" /> </script>'."\n");  //NOTE :: space in -" /> </script>- weirdly required
                    }
                }
            }
  
            $fileContent=$dom->html();
            
            
            if(extension_loaded('tidy')){
                $tidy = new tidy;
                $config = array('indent'=> true,'output-xhtml' => false, 'output-html' => true,'wrap'=> 600);
                $tidy->parseString($fileContent, $config, 'utf8');
                $tidy->cleanRepair();
                $fileContent=$tidy.'';
            }
    
            $fileContent=str_replace('href="%24','href="$',$fileContent);
            $fileContent=str_replace('action="%24','action="$',$fileContent); //for some unkonown reason phpQuery changes the $ to %24 in action so this was required fix that

            file_put_contents($filePath, $fileContent);
        } 

        function updateLayoutFile($domOrfileContent, $method, $pathOrObject){

            if($pathOrObject instanceof SC_Element){
                $filePath = $this->layoutPath($pathOrObject);
            }elseif(is_string($pathOrObject)){
                $filePath = $pathOrObject;
            }

            $dom = QP::withHTML5(file_get_contents($filePath));

            if (is_string($domOrfileContent)) {
                $newContent = QP::withHTML5($domOrfileContent);
            } elseif ($domOrfileContent instanceof QueryPath\DOMQuery) {
                $newContent = $domOrfileContent;
            }

            $dom->find('.'.$method)->replaceWith($newContent);

            // Get The CSS and JS of the base Tamplate
            $this->getStylesAndScriptsLinks($dom);

            // Add all the CSS and JS (from Datas -previously collected- and the base template)

            if ($dom->find('head')->html() != '') {
                $dom->find('head link[rel="stylesheet"]')->remove();
    
                foreach(self::$csslinks as $csslink){

                    if(substr($csslink, 0, 4) == 'http' && substr($csslink, -4) == '.css'){
                        $dom->find('head')->append('<link rel="stylesheet" href="'.$csslink.'"  //>');
                    }else{
                        $dom->find('head')->append('<link rel="stylesheet" href="'.$this->cssWebRoot.'/'.basename($csslink).'" //>');
                    }
                }
                $dom->find('head script')->remove();

                foreach(self::$jslinks as $jslink){
                    if (substr($jslink, 0, 4) == 'http' && substr($jslink, -3) == '.js') {
                        $dom->find('head')->append('<script type="text/javascript" src="'.$jslink.'"> </script>'."\n");
                    } else {
                        $dom->find('head')->append('<script type="text/javascript" src="'.$this->jsWebRoot.'/'.basename($jslink).'" /> </script>'."\n");  //NOTE :: space in -" /> </script>- weirdly required
                    }
                }
            }
  
            $fileContent=$dom->html();
            
            
            if(extension_loaded('tidy')){
                $tidy = new tidy;
                $config = array('indent'=> true,'output-xhtml' => false, 'output-html' => true,'wrap'=> 600);
                $tidy->parseString($fileContent, $config, 'utf8');
                $tidy->cleanRepair();
                $fileContent=$tidy.'';
            }
    
            $fileContent=str_replace('href="%24','href="$',$fileContent);
            $fileContent=str_replace('action="%24','action="$',$fileContent); //for some unkonown reason phpQuery changes the $ to %24 in action so this was required fix that

            file_put_contents($filePath, $fileContent);
        } 

        function appendToLayoutFile($domOrfileContent, $pathOrObject){
            if($pathOrObject instanceof SC_Element){
                $filePath = $this->layoutPath($pathOrObject);
            }elseif(is_string($pathOrObject)){
                $filePath = $pathOrObject;
            }
            $dom = QP::withHTML5(file_get_contents($filePath));
        

            if(is_string($domOrfileContent)){
                $newContent = QP::withHTML5(file_get_contents($domOrfileContent));
            }elseif($domOrfileContent instanceof QueryPath\DOMQuery ){
                $newContent = $domOrfileContent;
            }

            if ($dom->find('#content')->html() != '') {
                $dom->find('#content')->append($newContent);
            } else {
                $dom->find('body')->append($newContent);
            }
            

            // Get The CSS and JS of the base Tamplate
            $this->getStylesAndScriptsLinks($dom);

            // Add all the CSS and JS (from Datas -previously collected- and the base template)
            if($dom->find('head')->html() != '') {
                $dom->find('head link[rel="stylesheet"]')->remove();
    
                foreach(self::$csslinks as $csslink){
                    if (substr($csslink, 0, 4) == 'http' && substr($csslink, -4) == '.css') {
                        $dom->find('head')->append('<link rel="stylesheet" href="'.$csslink.'"  //>');
                    } else {
                        $dom->find('head')->append('<link rel="stylesheet" href="'.$this->cssWebRoot.'/'.basename($csslink).'" //>');
                    }
                }
                $dom->find('head script')->remove();
                foreach (self::$jslinks as $jslink) {
                    if (substr($jslink, 0, 4) == 'http' && substr($jslink, -3) == '.js') {
                        $dom->find('head')->append('<script type="text/javascript" src="'.$jslink.'"> </script>'."\n");
                    } else {
                        $dom->find('head')->append('<script type="text/javascript" src="'.$this->jsWebRoot.'/'.basename($jslink).'" /> </script>'."\n");  //NOTE :: space in -" /> </script>- weirdly required
                    }
                }                
            }
  
            $fileContent=$dom->html();
            
            
            if(extension_loaded('tidy')){
                $tidy = new tidy;
                $config = array('indent'=> true,'output-xhtml' => false, 'output-html' => true,'wrap'=> 600);
                $tidy->parseString($fileContent, $config, 'utf8');
                $tidy->cleanRepair();
                $fileContent=$tidy.'';
            }
   
            $fileContent=str_replace('href="%24','href="$',$fileContent);
            $fileContent=str_replace('action="%24','action="$',$fileContent); //for some unkonown reason phpQuery changes the $ to %24 in action so this was required fix that

            file_put_contents($filePath, $fileContent);
     
        } 

        function writeLayoutFile($domOrfileContent, $pathOrObject){
            if($pathOrObject instanceof SC_Element){
                $filePath = $this->layoutPath($pathOrObject);
            }elseif(is_string($pathOrObject)){
                $filePath = $pathOrObject;
            }
            


            if(is_string($domOrfileContent)){
                $dom = QP::withHTML5($domOrfileContent);
            }elseif($domOrfileContent instanceof QueryPath\DOMQuery ){
                $dom = $domOrfileContent;
            }

            $baseTemplate = QP::withHTML5(file_get_contents($this->layoutPath('SC_Element')));
            $baseTemplate->find('body')->html($dom->html());    
            // Get The CSS and JS of the base Tamplate
            $this->getStylesAndScriptsLinks($baseTemplate);

            
            // Add all the CSS and JS (from Datas -previously collected- and the base template)
            if($dom->find('head')->html() != '') {
                $dom->find('head link[rel="stylesheet"]')->remove();
    
                foreach(self::$csslinks as $csslink){
                    if (substr($csslink, 0, 4) == 'http' && substr($csslink, -4) == '.css') {
                        $dom->find('head')->append('<link rel="stylesheet" href="'.$csslink.'"  //>');
                    } else {
                        $dom->find('head')->append('<link rel="stylesheet" href="'.$this->cssWebRoot.'/'.basename($csslink).'" //>');
                    }
                }
                $dom->find('head script')->remove();
                foreach (self::$jslinks as $jslink) {
                    if (substr($jslink, 0, 4) == 'http' && substr($jslink, -3) == '.js') {
                        $dom->find('head')->append('<script type="text/javascript" src="'.$jslink.'"> </script>'."\n");
                    } else {
                        $dom->find('head')->append('<script type="text/javascript" src="'.$this->jsWebRoot.'/'.basename($jslink).'" /> </script>'."\n");  //NOTE :: space in -" /> </script- weirdly required
                    }
                }
            }
  
            $fileContent=$baseTemplate->html();
    
            if(extension_loaded('tidy')){
                $tidy = new tidy;
                $config = array('indent'=> true,'output-xhtml' => false, 'output-html' => true,'wrap'=> 600);
                $tidy->parseString($fileContent, $config, 'utf8');
                $tidy->cleanRepair();
                $fileContent=$tidy.'';
            }
    
            $fileContent=str_replace('href="%24','href="$',$fileContent);
            $fileContent=str_replace('action="%24','action="$',$fileContent); //for some unkonown reason phpQuery changes the $ to %24 in action so this was required fix that

            file_put_contents($filePath, $fileContent);
        }       
 
    function fillDatasInElementLayout(SC_Element $object, QueryPath\DOMQuery  $dom, string $method){
        $ret = $dom->html();
        $datasInLayout = preg_match_all_callback(
            '/EA_[a-z,_,0-9]+/i',
            $ret,
            function ($match){
                return explode('_',$match[0]);
            }
        );

        if($datasInLayout){
            //$datasInLayout = array_unique($datasInLayout);

            foreach($datasInLayout as $dataInLayout){
                $data = $object->{'O'.$dataInLayout[1]}();
                $dataMethodClass = '';
                if(isset($dataInLayout[2])){
                    $dataMethod=$dataInLayout[2];
                    $dataMethodClass=$dataInLayout[2];
                    $join='_';
                }else{
                    $dataMethod=$method;
                    $join='';
                };
                QP($dom, ".EA_" . $data->name() . $join . $dataMethodClass)->replaceWith($this->renderData($data, $dataMethod, QP($dom, ".EA_" . $data->name() . $join . $dataMethodClass)));

            }
        }

        return $dom;
    }

    function fillDomWithObject($object, voku\helper\HtmlDomParser $dom, $action=null){
        $filledItemHtml='';
        $repeatNodes = $dom->find(".repeat");

        foreach($repeatNodes as $repeatNode){
            $repeatClasses = explode(' ', $repeatNode->class);
            $items = $object->{$repeatClasses[1]}();
            $itemHtml = $repeatNode->find('.repeat .item',0);
            $selectedItemHtml = $repeatNode->find('.repeat .selectedItem',0);

            foreach($items as $key => $value){
                if (is_object($value)) {
                    if( ($value instanceof \SD_Data AND $value->val() == $object->val()) 
                        OR 
                        ($value instanceof \SC_Element AND $value->id() == $object->val())
                    ){
                        $filledItemHtml .= $this->fillDomWithObject($value, $selectedItemHtml)->outerHtml;
                    }else{
                        $filledItemHtml .= $this->fillDomWithObject($value, $itemHtml)->outerHtml;
                    }
                }else{
                    if( $key === 0 ){ $key = '0'; }else
                    if( $key === ' ' ){ $key = ''; }
                    if($selectedItemHtml && (strval($object->val()) === strval($key) OR strval($object->val()) === strval($value))){
                        $filledItemHtml .= "\n".str_replace(['$key','$val'],[$key,$value],$selectedItemHtml->outerHtml);
                    }else{
                        $filledItemHtml .= "\n".str_replace(['$key','$val'],[$key,$value],$itemHtml->outerHtml);
                    }
                }
            }

            $repeatNode->innerHtml = "\n".$filledItemHtml."\n";
        }

        $ret = preg_replace_callback(
            '/(\$)([a-z,_,0-9]+)/i',
            function ($matches) use ($object){
                $parameters = explode ('_',$matches[2]);
                $method = array_shift($parameters);
                $methodKey = substr($method, 0, 2);
                $rendermethod = substr($method, 2); 

                if($methodKey != 'SR' && (method_exists($object,$method) OR property_exists($object, $method)) ){ 
                    // if the method is for the data call it
                    return call_user_func_array(array($object, $method), $parameters);
                }elseif($methodKey == 'SR' && (method_exists($this,$rendermethod) OR property_exists($this, $rendermethod)) ){
                    // if the method is for the render call it and use data as a parameter
                    if(method_exists($this,$rendermethod)){
                        array_unshift($parameters,$object);
                    }
                    return call_user_func_array(array($this, $rendermethod), $parameters);
                }elseif($object->parent()){
                    return call_user_func_array(array($object->parent(), $method), $parameters);
                }
            },
            $dom
        );
        
        return HtmlDomParser::str_get_html($ret);
    }
        // $repeatClasses = explode(' ', QP($dom, '.repeat')->attr('class'));

        // if($repeatClasses[0]){
        //     $items = $Data->{$repeatClasses[1]}();
        //     $itemHtml = QP($dom, '.repeat .item:first')->html();
        //     $filledItemHtml = '';
        //     $selectedItemHtml = QP($dom, '.repeat .selectedItem:first')->html();
        //     if(is_string(reset($items)) OR is_numeric(reset($items))){
        //         if (!$itemHtml) { $itemHtml = QP($dom, '.repeat *:first')->html(); }
        //         foreach($items as $key => $value){
        //             if( $key === 0 ){ $key = '0'; }else
        //             if( $key === ' ' ){ $key = ''; }
        //             if($selectedItemHtml && (strval($Data->val()) === strval($key) OR strval($Data->val()) === strval($value))){
        //                 $filledItemHtml .= str_replace(['$key','$val'],[$key,$value],$selectedItemHtml);
        //             }else{
        //                 $filledItemHtml .= str_replace(['$key','$val'],[$key,$value],$itemHtml);
        //             }
        //         }
        //         $dom['.repeat']->html($filledItemHtml);
     
        //     }elseif(reset($items) instanceof SD_Data){
        //         //TODO
        //     }
        // }


        ////// $ret = str_replace('%24','$',$dom->html()); 
    //$ret = str_replace('="%24','="$',$dom->html());
    //$ret = str_replace('/%24','/$',$ret);  //for some unkonown reason phpQuery changes the $ to %24  so this was required fix that

       

        /** Quick and dirty replace of action */
    ////$ret = str_replace('$action',$action,$ret); //for some unkonown reason 

        /*
        Substitute the $variables on the Layout with the correspondant Data value.
        Substitute the $SRvariables with the correspondant Renderer Method
        */


        /*
        Remove/fix the required attributes
        */
        // $dom = QP::withHTML5($ret);
        // /** @var QueryPath\DOMQuery $dom */        
        // QP($dom, "*[required='']")->removeAttr('required');
        // $this->getStylesAndScriptsLinks($dom);
        // return $dom;
    // }

    function fillVariablesInObjectLayout($object, $layout){

        return $ret->outerHTML;
    }



    function fillVariablesInElementLayout( SC_Element $element, QueryPath\DOMQuery  $dom, $action=null,$nextStep=null){   

        $ret=$dom->html();
        $fixedActionInDom = str_replace('action="%24','action="$',$dom->html());
        $fixedActionInDom = str_replace('/%24','/$',$fixedActionInDom);
        $fixedActionInDom = str_replace('href="%24','href="$',$fixedActionInDom); //for some unkonown reason phpQuery changes the $ to %24 in action so this was required fix that

        /** Quick and dirty replace of action */
        $fixedActionInDom = str_replace('$action',$action,$fixedActionInDom);  

        $ret = preg_replace_callback(
            '/(\$)([a-z,_,0-9]+)/i',
            function ($matches) use ($element,$nextStep){
                // return $matches[2];
                $parameters = explode ('_',$matches[2]);
                $method = array_shift($parameters);
                $methodKey = substr($method, 0, 2);
                $rendermethod = substr($method, 2); 

                if($methodKey != 'SR' && method_exists($element,$method)){
                    return call_user_func_array(array($element, $method), $parameters);
                }elseif($methodKey == 'SR' &&  (method_exists($this,$rendermethod) OR property_exists($this, $rendermethod))){
                    array_unshift($parameters,$element);
                    if($nextStep){
                        $parameters[]=$nextStep;
                    }
                    return call_user_func_array(array($this, $rendermethod), $parameters);
                }else{
                    return call_user_func_array(array($element, $method), $parameters);
                }
            },
            $fixedActionInDom 
        );
        $dom = QP::withHTML5($ret);
        /** @var QueryPath\DOMQuery $dom */
        QP($dom, "*[required='']")->removeAttr('required');
        $this->getStylesAndScriptsLinks($dom);

        return $dom;
    }


	function setMessage($message='') {
		SC_Main::$SystemMessage = $message;
	}


	function encodeURL($class = null, $construct_params = null, $method = null, $method_params = null, $dataName = null) {
		$url = '';
		if(isset($class)) {
			// class
			$url.= $this->App_web_root . '/' . $this->fixCode(strtr($class,'\\','-'));
			// construct params
			if(!empty($construct_params) && is_array($construct_params)) {
                // $tempArr=array_map(
                //     //['self', 'parameterEncoder'], 
                //     'SR_main::parameterEncoder()',
				// 	$construct_params
				// );
                $tempArr = array();
                foreach($construct_params as $param){
                    $tempArr[]=SR_main::parameterEncoder($param);
                }
			    $url.= '/' . implode('/',$tempArr);
			}
			
			if(isset($dataName) && isset($method)) {
				// Data name
				$url.= $this->URL_METHOD_SEPARATOR . $dataName;
			}
			
			if(isset($method)) {
				// method
				$url.= $this->URL_METHOD_SEPARATOR . $method;
				
				// method params
				if(!empty($method_params) && is_array($method_params)) {
                    $tempArr = array();
                    foreach($method_params as $param){
                        $tempArr[]=SR_main::parameterEncoder($param);
                    }
                    $url.= '/' . implode('/',$tempArr);
				}
			}
		}
        $qs = SC_Main::$URL_METHOD_SEPARATOR;

        if(!empty(SC_Main::$SystemMessage)){ $url.=$qs.$qs.SR_main::parameterEncoder(SC_Main::$SystemMessage); }
		return $url;
	}

    static function fixCode($string, $encoding = true) {
		return $encoding  
			? strtr($string, array(
				'%2F' => '/',
				'%2527' => '%252527',
				'%27' => '%2527',
				'%255C' => '%25255C',
				'%5C' => '%255C',
			))
			: strtr($string, array(
				'%2527' => '%27',
				'%252527' => '%2527',
				'%255C' => '%5C',
				'%25255C' => '%255C',
			));
	}

    function lookForMethodInElementsTree(SC_Element $element, string $method){
        $Tree = class_parents($element);
        $Tree = array_merge(array($element->getClass()), array_values($Tree));
        $i = '0';
        $Dom = '';
        do {     
            if($Tree[$i] != 'SC_Element' ){
                $path = $this->layoutsPath($Tree[$i]);
                if(file_exists($path)){
                    $Dom = \phpQuery::newDocumentFileHTML($path);
                }
            }elseif($Tree[$i] == 'SC_Element'){
                $Dom = $this->LoadDefaultLayoutFile();
            }
            $i++;
        } while ($i < sizeof($Tree) && (!$Dom || empty(QP($Dom, ".$method")->html())));

        $this->getStylesAndScriptsLinks($Dom);
        $this->addStylesTagsToAutoCSS($element, $Dom, $method);
        return $Dom[".$method"];     
    }


    public function action(SC_BaseObject $object, string $action, $clean = null, $message = null){

        if($object instanceof SD_ElementContainer OR $object instanceof SD_ElementsContainerMM){
            $object = $object->element();
        }
        if($clean == 'id'){
            return $this->encodeURL($object->getClass(),array(),$action);
            //return $this->encodeURL($object->getClass(),array(),$action,array($nextStep));    
        }else{
            return $this->encodeURL($object->getClass(),array($object->id()),$action);
            //return $this->encodeURL($object->getClass(),array($object->id()),$action,array($nextStep));
        }
    }


    function layoutsPath($object){
        if(is_a($object,'SD_Data')){
            $dataPath=DIRECTORY_SEPARATOR.'Datas';
            $ancestors = class_parents($object);
            array_splice($ancestors, -1);
            $ancestorClass = $object->getClass();
            while(
                    $ancestorClass 
                    && !file_exists($this->App_path.DIRECTORY_SEPARATOR.'Layouts'.$dataPath.DIRECTORY_SEPARATOR.$ancestorClass.'.html')
                    && !file_exists($this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.$dataPath.DIRECTORY_SEPARATOR.$ancestorClass.'.html')
                ){
                $ancestorClass = array_shift($ancestors);
            }
            if(file_exists($this->App_path.DIRECTORY_SEPARATOR.'Layouts'.$dataPath.DIRECTORY_SEPARATOR.$ancestorClass.'.html')){
                $ret = $this->App_path.DIRECTORY_SEPARATOR.'Layouts'.$dataPath.DIRECTORY_SEPARATOR.$ancestorClass.'.html';
            }elseif(file_exists($this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.$dataPath.DIRECTORY_SEPARATOR.$ancestorClass.'.html')){
                $ret = $this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.$dataPath.DIRECTORY_SEPARATOR.$ancestorClass.'.html';
            }
        }elseif(!is_string($object) && is_a($object,'SC_Element')){
            $ret = $this->App_path.DIRECTORY_SEPARATOR.'Layouts'.DIRECTORY_SEPARATOR.$object->getClass().'.html';
            if(!file_exists($ret)){
                $ret = $this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.DIRECTORY_SEPARATOR.$object->getClass().'.html';
            }
        }elseif(is_string($object)){

            $ret = $this->App_path.DIRECTORY_SEPARATOR.'Layouts'.DIRECTORY_SEPARATOR.$object.'.html';

            if( is_subclass_of($object, 'SD_Data') || $object === 'SD_Data' ){ $dataPath=DIRECTORY_SEPARATOR.'Datas'; }
            else{$dataPath='';}
            

            if(!file_exists($ret)){
                $ret = $this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.$dataPath.DIRECTORY_SEPARATOR.$object.'.html';
            }
        }

        return $ret;
    }


    /* methods related with getting/generatting the Layouts */
    function  LoadDefaultLayoutFile(){
        $simplonBase = $this->SimplOn_path.DIRECTORY_SEPARATOR.'Renderers'.DIRECTORY_SEPARATOR.$GLOBALS['redenderFlavor'].DIRECTORY_SEPARATOR.'htmls'.DIRECTORY_SEPARATOR.'SC_Element.html';
        $appBase = $this->App_path.DIRECTORY_SEPARATOR.'Layouts'.DIRECTORY_SEPARATOR.'SC_Element.html';
        if (file_exists($appBase)) { $dom = QP::withHTML5(file_get_contents($appBase)); }
        else { $dom = QP::withHTML5(file_get_contents($simplonBase)); }
        $this->getStylesAndScriptsLinks($dom);
        return $dom;
    }

	function link($content, $href, array $extra_attrs = array(), $auto_encode = true) {
		$extra = array();
		foreach($extra_attrs as $attr => $value) {
			if($auto_encode) $value = htmlentities($value, ENT_COMPAT, 'UTF-8');
			$extra[] = $attr.'="'.$value.'"';
		}
		if($auto_encode) {
			$href = htmlentities($href, ENT_COMPAT, 'UTF-8');
			//$content = htmlentities($content, ENT_COMPAT, 'UTF-8');
		}
		return '<a '.implode(' ',$extra).' href="'.$href.'">'.$content.'</a>';
    }



	static function parameterEncoder($p){
		if(is_string($p)) {
		    $string_delimiter = '\'';
			$p = self::fixCode(urlencode($p));
			return $string_delimiter. $p .$string_delimiter;
		} else {
			return urlencode($p);
		}
	}


}