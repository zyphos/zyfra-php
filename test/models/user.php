<?php
class user extends ObjectModel{
    function name_plus_id_fx($field, $ids, $context, $datas){
        $res = [];
        foreach($ids as $id){
            $row = &$datas[$id];
            $res[$id] = $row->name.'['.$row->id.']';
        }
        return $res;
    }

    function fx_parameter_fx($field, $ids, $context, $datas){
        $res = [];
        if (isset($context['parameter'])){
            $parameter = '['.$context['parameter'].']';
        }else{
            $parameter = '';
        }
        foreach($ids as $id){
            $row = &$datas[$id];
            $res[$id] = $row->{'name'.$parameter}.'['.$row->{'id'.$parameter}.']'.$parameter;
        }
        return $res;
    }

    function init(){
        $this->name = new TinytextField('Name');
        $this->language_id = new Many2OneField('Language', 'language', ['back_ref_field'=>'user_ids']);
        $this->group_ids = new Many2ManyField('Groups', 'user_group');
        $this->can_action_ids = new Many2ManyField('Can actions', 'can_action');
        $this->name_plus_id = new FunctionField('Name + plus', [$this, 'name_plus_id_fx'], ['required_fields'=>['id','name']]);
        $this->name_plus2_id = new FunctionField('Name + plus', [$this, 'name_plus_id_fx'], ['required_fields'=>['id','name']]);

        $this->fx_param = new FunctionField('Fx parameter', [$this, 'fx_parameter_fx'], ['required_fields'=>['id','name']]);
    }
}
