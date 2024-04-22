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
 * Alphanumeric data type  
 * 
 * This is an alphanumeric data type which allow you display an input which only 
 * get alphanumeric characters.
 * 
 * @author Rubén Schaffer Levine and Luca Lauretta <http://simplonphp.org/>
 * @copyright (c) 2011, Rubén Schaffer Levine and Luca Lauretta
 * @category Data
 */
class SD_Alphanumeric extends SD_String {
    var $noAlphaNumeric = 'Non alphanumeric value received.';
    /**
         * 
         * function val - this function verifies if $val is defined and isn't null
         * 
         * if is true then verifies if $val only has alphanumeric characters and save
         * $val into $this->val but if $val has also letters then throw an user error.
         * 
         * if is false then just return $this->val.
         * 
         * @param string $val - value received
         * @return string
         */
    function val($val = null) {
        if(isset($val)){
            if(!$this->fixedValue){
                if(ctype_alnum($val))
                    $this->val = $val;
                else
                    throw new SC_DataValidationException ($this->noAlphaNumeric);
            }
        }else{
            return $this->val;
        }
    }
}