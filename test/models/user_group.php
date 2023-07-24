<?php
class user_group extends ObjectModel{
    function init(){
        $this->name = new TinytextField('Name');
        $this->user_ids = new Many2ManyField('Users', 'user');
        $this->can_action_ids = new Many2ManyField('Can actions', 'can_action');
    }
}
