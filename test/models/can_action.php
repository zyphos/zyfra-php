<?php
class can_action extends ObjectModel{
    function init(){
        $this->name = new TinytextField('Name');
        $this->user_ids = new Many2ManyField('Users', 'user');
        $this->group_ids = new Many2ManyField('Groups', 'user_group');
    }
}
