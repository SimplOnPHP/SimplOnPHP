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
namespace DOF\Datas;

class DeleteAction extends ElementLink {

	public function __construct($label, array $sources, $flags=null, $searchOp=null){
		parent::__construct($label, $sources, 'showDelete', array(), $flags,null,$searchOp);
	}
	
	function parent($parent = null) {
		if($parent) {
			$this->parent = $parent;
			$this->method = $parent->quickDelete ? 'processDelete' : 'showDelete';
		}
		return $parent;
	}
	
	function htmlClasses($append = '', $nestingLevel = null) {
        return parent::htmlClasses(($this->parent->quickDelete?'ajax ':'lightbox ').$append, $nestingLevel);
    }
	
	function cssSelector($append = '', $nestingLevel = null) {
        return parent::cssSelector(($this->parent->quickDelete?'.ajax':'.lightbox').$append, $nestingLevel);
    }
}