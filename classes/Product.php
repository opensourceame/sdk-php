<?php
/**
 * @copyright    give.it 2013
 * @author       David Kelly
 *
 * required:
 * - PHP > 5.3.0
 * - libmcrypt >= 2.4.x
 */


namespace GiveIt\SDK;

class Product extends Base
{

    public      $renderErrors       = false;
    public      $data               = array();
    private     $requiredFields     = array(
                    'details:code'     => 'string:40',
                    'details:price'    => 'integer',
                    'details:name'     => 'string:200',
                    'details:image'    => 'string',
                 );

    public function __construct($data = Array())
    {
        if ($data){
            $this->data = $data;
        }
        $this->addGiveItData();

        return true;
    }

    /**
     * Set data for the product. This can be instead of the __construct function
     * to be able to set the product step by step
     */
    public function setProductDetails($data = Array())
    {
        foreach ($data as $key => $value){
            $this->data['details'][$key] = $value;
        }

        return $this;
    }
    
    public function setCurrency($iso_code = 'USD'){
        if (strlen($iso_code) == 3){
            $this->data['currency'] = $iso_code;    
        } else {
            $this->addError("invalid currency $iso_code. Currency should be a 3-letter ISO code");
        }
        
    }
    
    /**
     * Add an option for the buyer
     * @return reference
     */
    public function addBuyerOption($option){
        return $this->addOption('buyer', $option);
    }

    /**
     * Add an option for the recipient
     * @return reference
     */
    public function addRecipientOption($option){

        // strip out prices on option and any choices
        $option = $this->removePrice($option);

        return $this->addOption('recipient', $option);
    }

    private function removePrice($option) {

        if (isset($option->price)) {

            if ($option->price != 0) {
                trigger_error("removing non-zero price ($option->price) from recipient option ($option->id)", E_USER_WARNING);
            }

            unset($option->price);
        }

        if (isset($option->choices)) {
            foreach ($option->choices as $choice) {
                $this->removePrice($choice);
            }
        }

        return $option;
    }

    private function addOption($type, $option){
        if (is_array($option)){
            foreach ($option as $o) {
                $this->addOption($type, $o);
            }
        } else {
            if (isset($this->data['options'][$type][$option->id])) {
                $this->addError("cannot add option with duplicate id $option->id");
                return false;
            }

            $this->data['options'][$type][$option->id] = $option;
        }

        return $this;
    }

    /**
     * Validate that the product data is being given in a valid format
     * Flatten the array and check for the existence of required fields
     */
    public function validate()
    {
        $flat   = $this->flatten($this->data);
        $valid  = true;

        foreach ($this->requiredFields as $fieldName => $fieldType) {

            if (! isset($flat[$fieldName])) {
                $this->addError("missing field $fieldName");
                $valid = false;
                continue;
            }

            $result = $this->validateFieldType($fieldType, $flat[$fieldName]);

            if ($result !== true) {
                $this->addError("$fieldName - $result");
                $valid = false;
            }
        }

        return $valid;
    }

    private function validateFieldType($type, $value)
    {
        if (strpos($type, ':') !== false) {
            list($type, $param) = explode(':', $type);
        }

        switch ($type) {
            case 'integer':

                if (is_int($value)) {
                   return true;
                }

                return 'must be an integer';

            case 'string':

                if (! is_string($value)) {
                    return 'must be a string';
                }

                if (isset($param)) {

                    if (strlen($value) <= $param) {
                        return true;
                    }

                   return "must be no more than $param characters";

                }

                return true;
        }

        return false;
    }

    private function addGiveItData()
    {
        $this->data['give.it'] = array(
          'md5'          => md5(serialize($this->data)),
          'rendered_at'  => date('Y-m-d H:i:s') . ' ' . microtime(true),
          'sdk_version'  => 'PHP ' . \GiveIt\SDK::VERSION,
        );

        return true;
    }

   /**
    * Generate Button HTML
    *
    * This function generates the necessary HTML to render the button.
    *
    */
    public function getButtonHTML($buttonType = 'blue_rect_sm')
    {
        if (! $this->validate()) {
            $this->addError("product data is invalid");
            return $this->getErrorsHTML();
        }

        $parent     = \GiveIt\SDK::getInstance();
        $crypt      = \GiveIt\SDK\Crypt::getInstance();

        if ($parent == false) {
            $this->addError("SDK must be instantiated to render buttons");
            return false;
        }

        $encrypted  = $crypt->encode(json_encode($this->data), $parent->dataKey);

        if ($encrypted == false) {

            if (! $this->renderErrors) {
                return false;
            }

            $this->addError($crypt->errors());

            return $this->getErrorsHTML();
        }

        // $encrypted  = urlencode($encrypted);

        $html = "<span class='giveit-button' data-giveit-buttontype='$buttonType' data-giveit-data='$encrypted'></span>";

        return $html;
    }

    public function getErrorsHTML()
    {
        if (! $this->renderErrors) {
            return false;
        }

        $html = "\n<span class='giveit-button'>";

        foreach ($this->errors() as $error) {
            $html .= "\n\t<span class='giveit-error'>$error</span>";
        }

        $html .= "\n</span>\n";

        return $html;
    }

}
