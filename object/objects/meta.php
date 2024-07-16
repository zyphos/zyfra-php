<?php
class meta_dimensions extends ObjectModel{
    // Length, temperature, force, weight, ...
    function init(){
        $this->name = new CharField('Name', 50);
    }
}

class meta_units extends ObjectModel{
    // m, mm, cm, dm, kg, ...
    function init(){
        $this->name = new CharField('Name', 50);
        $this->dimension_id = new Many2OneField('Dimension', 'meta_dimensions', ['back_ref_field'=>['Units','unit_ids']]);
        $this->factor = new FloatField('Factor'); //Ratio to SI unit
    }
}

class meta_columns extends ObjectModel{
    // Length, Width, Height, Max weight, ...
    function init(){
        $this->name = new CharField('Name', 50);
        $this->label = new CharField('Label', 50, ['translate'=>true]);
        $this->tof = new IntSelectField('Type of field', [1=>'float', 2=>'int', 3=>'txt']);
        $this->dimension_id = new Many2OneField('Dimension', 'meta_dimensions');
    }
}
