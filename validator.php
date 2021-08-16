<?php
/*****************************************************************************
 *
 *		 Validator of data
 *		 ---------------
 *
 *		 Class to validate data (Anti-spam too)
 *
 *    Copyright (C) 2010 De Smet Nicolas (<http://ndesmet.be>).
 *    All Rights Reserved
 *
 *
 *    This program is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation, either version 3 of the License, or
 *    (at your option) any later version.
 *
 *    This program is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *****************************************************************************/

/*****************************************************************************
 * Quick Usage:
 * ------------
 *
 * $validator = new zyfra_validator;
 * $is_valid = $validator->is_valid($data, $field_type, $check_spam);
 *
 * $data : the data to verify (string)
 * $field_type:
 * 	'int': integer
 * 	'float': float
 * 	'string': string
 * 	'phone': phone number
 * 	'email': email
 * 	'email_net': check email against the MX DNS record
 * 	'vat': vat number
 * 	'vat_net': check vat number against http://ec.europa.eu/
 *****************************************************************************/

class zyfra_validator {
    function is_valid($data, $data_type, $spam_check = False){
        $class = 'zyfra_v_'.$data_type;
        if (!isset($this->{$class})){
            if (!class_exists($class)) throw new Exception('Unsupported data type');
            $this->{$class} = new $class();
        }
        return $this->{$class}->is_valid($data, $spam_check);
    }
}

abstract class zyfra_validator_type {

    abstract function is_valid($data, $spam_check);

    function is_regex_valid($regex, $data){
        if (preg_match($regex, $data, $matches) == 0) return false;
        return $matches[0] == $data;
    }
}

class zyfra_v_int extends zyfra_validator_type{
    function is_valid($data, $spam_check){
        $regex = '/[0-9]*/';
        return $this->is_regex_valid($regex, $data);
    }
}

class zyfra_v_float extends zyfra_validator_type{
    function is_valid($data, $spam_check){
        $regex = '/[0-9.]*/';
        return $this->is_regex_valid($regex, $data);
    }
}

class zyfra_v_string extends zyfra_validator_type{
    var $spam_words = array('cialis','viagra');

    function is_valid($data, $spam_check){
        if ($spam_check){
            return !$this->is_spam($data);
        }
        return true;
    }

    function is_spam($data){
        /*
         * Check for spam in $data
         * return true if spam
         * return false if safe
         */
        //if (preg_match('/[a-z][A-Z]/', $data, $matches) > 0) return true;
        $value = strtolower($data);
        foreach($this->spam_words as $word){
            if (strpos($value,$word) !== false) return true;
        }
        return false;
    }
}

class zyfra_v_phone extends zyfra_validator_type{
    function is_valid($data, $spam_check){
        $regex = '/[()0-9.\-\/\+ ]*/';
        return $this->is_regex_valid($regex, $data);
    }
}

class zyfra_v_email extends zyfra_validator_type{
    function is_valid($data, $spam_check){
        $at_pos = strrpos($data, "@");
        if (is_bool($at_pos) && !$at_pos)
        {
            return false;
        }
        else
        {
            $domain = substr($data, $at_pos+1);
            $local = substr($data, 0, $at_pos);
            $local_len = strlen($local);
            $domain_len = strlen($domain);
            if ($local_len < 1 || $local_len > 64)
            {
                // local part length exceeded
                return false;
            }
            else if ($domain_len < 1 || $domain_len > 255)
            {
                // domain part length exceeded
                return false;
            }
            else if ($local[0] == '.' || $local[$local_len-1] == '.')
            {
                // local part starts or ends with '.'
                return false;
            }
            else if (preg_match('/\\.\\./', $local))
            {
                // local part has two consecutive dots
                return false;
            }
            else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain))
            {
                // character not valid in domain part
                return false;
            }
            else if (preg_match('/\\.\\./', $domain))
            {
                // domain part has two consecutive dots
                return false;
            }
            else if ($domain[0] == '.' || $domain[$domain_len-1] == '.')
            {
                // local part starts or ends with '.'
                return false;
            }
            else if
            (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/',
                    str_replace("\\\\","",$local)))
            {
                // character not valid in local part unless
                // local part is quoted
                if (!preg_match('/^"(\\\\"|[^"])+"$/',
                        str_replace("\\\\","",$local)))
                {
                    return false;
                }
            }
        }
        return true;
    }
}

class zyfra_v_email_net extends zyfra_v_email{
    // Check email against MX DNS record
    function is_valid($data, $spam_check){
        if (!parent::is_valid($data, $spam_check)) return false;
        list($user, $domain) = explode('@',$data);
        return count(dns_get_record($domain, DNS_MX)) > 0;
    }
}

class zyfra_v_url extends zyfra_validator_type{
    function is_valid($data, $spam_check){
        $regex = '|^https?://([-\w\.]+)+(:\d+)?(/([\w/_\-\.]*(#\S+)?(\?\S+)?)?)?$|i';
        return $this->is_regex_valid($regex, $data);
    }
}

class zyfra_v_vat extends zyfra_validator_type{
    function is_valid($data, $spam_check){
        $rx = array();
        //source: http://ec.europa.eu/taxation_customs/vies/faqvies.do#item11
        $rx[] = 'ATU\d{8}';
        $rx[] = 'BE0\d{9}';
        $rx[] = 'BG\d{9,10}';
        $rx[] = 'CY\d{8}L';
        $rx[] = 'CZ\d{8,10}';
        $rx[] = 'DE\d{9}';
        $rx[] = 'DK(\d{2} ?){3}\d\d';
        $rx[] = 'EE\d{9}';
        $rx[] = 'EL\d{9}';
        $rx[] = 'ES[A-Z0-9]\d{7}[A-Z0-9]';
        $rx[] = 'FI\d{8}';
        $rx[] = 'FR[A-HJ-NP-Z0-9]{2} ?\d{9}';
        $rx[] = 'GB\d{3} ?\d{4} ?\d\d( ?\d{3})?';
        $rx[] = 'GB(GD|HA)\d{3}';
        $rx[] = 'HU\d{8}';
        $rx[] = 'IE\d[A-Z0-9]\d{5}[A-Z]';
        $rx[] = 'IT\d{11}';
        $rx[] = 'LU\d{8}';
        $rx[] = 'LV\d{11}';
        $rx[] = 'MT\d{8}';
        $rx[] = 'NL\d{9}B\d{2}';
        $rx[] = 'PL\d{10}';
        $rx[] = 'PT\d{9}';
        $rx[] = 'RO\d{2,10}';
        $rx[] = 'SE\d{12}';
        $rx[] = 'SI\d{8}';
        $rx[] = 'SK\d{10}';
        $regex = '/'.implode('|', $rx).'/i';
        return $this->is_regex_valid($regex, $data);
    }
}


class zyfra_v_vat_net extends zyfra_v_vat{
    /*
     * Check VAT trought SOAP service of Taxation and Customs Union
     *
     * See Disclaimer at http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl
     *
     * http://ec.europa.eu/taxation_customs/vies/faqvies.do#item16
     */
    function is_valid($data, $spam_check){
        if (!parent::is_valid($data, $spam_check)) return false;
        $client = new SoapClient("http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl");
        $country = substr($data,0,2);
        $vat = substr($data,2);
        $params = (object)array('countryCode'=>$country, 'vatNumber'=>$vat);
        $result = $client->checkVat($params);
        unset($client);
        return $result->valid;
    }
}

?>
