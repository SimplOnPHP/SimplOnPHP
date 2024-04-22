<?php
/*
	Copyright © 2011 Rubén Schaffer Levine and Luca Lauretta <http://simplonphp.org/>
	
	This file is part of “SimplOn PHP”.
	
	“SimplOn PHP” is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation version 3 of the License.
	
	“SimplOn PHP” is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with “SimplOn PHP”.  If not, see <http://www.gnu.org/licenses/>.
*/



/**
* RadioButtonText data type
* 
* Displays radio buttons that are stored in the array options, then check if the 
* options are valid string and stores them in an array investing in securities 
* that have been selected.
* 
* @author Rubén Schaffer Levine and Luca Lauretta <http://simplonphp.org/>
* @copyright (c) 2011, Rubén Schaffer Levine and Luca Lauretta
* @category Data
*/

class SD_DorpdownBox extends SD_String
{
    /**
     *
     * @var array $options - This variable holds all options.
     * @var boolean $showValues - This variable shows the value of options.
     * @var string $valudationNotAnOption - Is a message to display just if the value introduced
     * isn't a string.
     */
	protected $options = array();
	protected $showValues = true;

	public $valudationNotAnOption='The value given is not a valid option';
	//public $valudationNaN = 'This field must be an integer number.';
         
        /**
         * function __contruct get the parameters to them in the parent construct
         * 
         * @param string $label
         * @param array $options
         * @param string $flags
         * @param string $val
         * @param string $filterCriteria
         */
	
	public function __construct($label=null, $options=array(), $flags=null, $val=null, $filterCriteria=null)
	{
		$this->options = $options;	
		parent::__construct($label, $flags, $val, $filterCriteria);
		//echo $this->showInput(true);
	}	
        
	/**
	 * 
	 * function val - This function checks if the value is a valid string in option. 
	 * if isn't throw an exception.
	 * 
	 * @param null $val
	 * @return void
	 * @throws SC_DataValidationException 
	 */
	public function val($val = null){
		if($val){
			if(!$this->fixedValue) {
				if(in_array($val, $this->options)){
					parent::val($val);
				}else{
					throw new SC_DataValidationException ($this->valudationNotAnOption);
				}
			}
		}else{
			return $this->val;
		}
	}

    //     /**
    //      * 
    //      * function showInput - This function prints the label and the input with the
    //      * correct format (id,class,name, value) to be used in the forms.
    //      * 
    //      * @param boolean $fill
    //      * @return string
    //      */
		
	// public function showInput($fill = false)
	// {
	// 	$data_id = 'SimplOn_'.$this->instanceId();
	// 	$ret=($this->label() ? '<label for="'.$data_id.'">'.$this->label().': </label>' : '');
	// 	$ret.='<select id="'.$data_id.'"  class="SimplOn '. $this->getClassName() .'" name="'. $this->name() .'" >';
	// 	$ret.='<option value="">---</option>';
    //     foreach($this->options as $value){
	// 		$ret.=($this->showValues ? $value:'').'<option value="'.$value.'"'.' '.(($fill && $this->val==$value)?' selected':'').'>'.$value.'</option>';
	// 	}
    //     $ret.='</select>';
	// 	return  $ret; 
	// }

    
	// public function getCSS($method) {
	// 	$a_css = parent::getCSS($method);
	// 	$a_css[] = SE_CSS::getPath("chosen.css");
	// 	return $a_css;
	// }



}