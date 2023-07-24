<?php
class language extends ObjectModel{
    function init(){
        $this->name = new CharField('Name', 2);
    }
}
