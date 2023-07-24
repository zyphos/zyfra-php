ORM: unit test
==============

<?php
function print_pre($txt){
    print_r($txt);
}
$document_root = dirname(dirname(__FILE__)).'/';

$config = (object)['db_type' => 'mysql',
                   'db_server' => 'localhost',
                   'db_name' => 'zyfra_orm_test',
                   'db_user' => 'zyfra_orm_test',
                   'db_password' => 'zyfra_orm_test',
                   ];

require_once($document_root.'object.php');

// Init pool
$o = Pool::get();
$o->set_model_path(dirname(__FILE__).'/models');
$o->instanciate_all_objects(); //Do not use lazy load because we need to create all table

function clear_database($o, $db){
    $object_names = $o->get_objects_in_pool();
    $table_names = [];
    foreach ($object_names as $object_name){
        $table_names[] = $o->$object_name->_table;
    }
    $db->query('DROP TABLE IF EXISTS '.implode(',', $table_names));
}


// Create tables
clear_database($o, $db);
$o->update_sql_structure();


// Do tests
$nb_passed = 0;
$nb_test = 0;

function check($result, $expected, $description){
    global $nb_test, $nb_passed;
    $nb_test++;
    printf('#%03d Testing %s...', $nb_test, $description);
    if ($result == $expected){
        echo "OK\n";
        $nb_passed++;
    }else{
        echo 'Failed, '.print_r($result, true).' != '.print_r($expected, true)."\n";
    }
}
        
// Check creation
$id = $o->language->create(['name'=> 'en']);
$o->language->create(['name'=> 'fr']);
$o->language->create(['name'=> 'nl']);
check($o->language->select('name'), [(object)['name'=>'en'],(object)['name'=>'fr'],(object)['name'=>'nl']], "single creation");

// Unlink one
$o->language->unlink($id);
check($o->language->select('name'), [(object)['name'=>'fr'],(object)['name'=>'nl']], "deletion by id");

// Unlink all
$o->language->unlink("1=1");
check($o->language->select('name'), [], "unlink all");

// Multiple creation
$o->language->create([['name'=>'en'],['name'=>'fr'],['name'=>'nl']]);
check($o->language->select('name'), [(object)['name'=>'en'],(object)['name'=>'fr'],(object)['name'=>'nl']], "multiple creation");

//Create dataset
$o->can_action->create([['name'=>'read'],
                        ['name'=>'create'],
                        ['name'=>'update'],
                        ['name'=>'delete'],
                        ['name'=>'approve'],
                        ]);
$o->user_group->create([['name'=>'reader',
                         'can_action_ids'=>[[4, 'read']]],
                        ['name'=>'writer',
                         'can_action_ids'=>[[6, 0, ['create','update']]]],
                        ['name'=>'admin',
                         'can_action_ids'=>[[6, 0, ['read','create','update','delete']]]],
                        ]);
$o->user->create([['name'=>'max',
                   'language_id'=>'fr',
                   'can_action_ids'=>[[4, 'approve']],
                   'group_ids'=>[[6, 0, ['reader','writer']]]],
                  ['name'=>'tom',
                   'language_id'=>'en',
                   'group_ids'=>[[4, 'admin']]]
                  ]);
// M2O
check($o->user->select("language_id.name AS name WHERE name='max'"),[(object)['name'=>'fr']], 'read M2O');

// M2O
check($o->language->select("user_ids.(name) WHERE name='en'"),[(object)['user_ids'=>[(object)['name'=>'tom']]]], 'read O2M');

// M2M
check($o->user->select('name,group_ids.(name) AS groups'),
   [(object)['name'=>'max',
             'groups'=>[(object)['name'=>'reader'],
                        (object)['name'=>'writer']]],
    (object)['name'=>'tom',
             'groups'=>[(object)['name'=>'admin']]]
   ], "read M2M");

// where M2M
check($o->can_action->select("name WHERE user_ids.name='max' OR group_ids.user_ids.name='max'"),
    [(object)['name'=>'read'],
     (object)['name'=>'create'],
     (object)['name'=>'update'],
     (object)['name'=>'approve'],
    ], 'where M2M');

// where M2M is null
check($o->user->select("name WHERE can_action_ids IS NULL"),
    [(object)['name'=>'tom']
    ], 'where M2M is null');

//is null
$o->user->select('name WHERE language_id IS NULL', ['debug'=>0]);

//Function field
check($o->user->select('id,name_plus_id,name_plus2_id', ['debug'=>0]),
    [(object)['id'=>1, 'name_plus_id'=>'max[1]', 'name_plus2_id'=>'max[1]'],
     (object)['id'=>2, 'name_plus_id'=>'tom[2]', 'name_plus2_id'=>'tom[2]'],
      ], 'function field');


//Function field with parameter
check($o->user->select('id,fx_param[fr]', ['debug'=>0]),
[(object)['id'=>1, 'fx_param_fr_'=>'max[1][fr]'],
 (object)['id'=>2, 'fx_param_fr_'=>'tom[2][fr]'],
  ],'function field with parameters');
/*
 * Active records
 */
//Create
$can_action = $o->can_action->active_record(['name'=>'test']);
$id = $can_action->save();
check($o->can_action->select(['name WHERE id=%s',[$id]]), [(object)['name'=>'test']], "active_record create");
$o->can_action->unlink($id);

//Create modify save
$can_action = $o->can_action->active_record(['name'=>'test2']);
$can_action->name = 'test3';
$id = $can_action->save();
check($o->can_action->select(['name WHERE id=%s',[$id]]), [(object)['name'=>'test3']], "active_record create-modify");

// modify save
$can_action = $o->can_action->active_record($id);
$can_action->name = 'test4';
$id = $can_action->save();
check($o->can_action->select(['name WHERE id=%s',[$id]]), [(object)['name'=>'test4']], "active_record modify-save");

// read
check($o->can_action->active_record($id)->name, 'test4', "active_record read");

// exists
check($o->can_action->active_record($id)->exists(), true, "active_record exists 1");
$o->can_action->unlink($id);
check($o->can_action->active_record($id)->exists(), false, "active_record exists 2");


//clear_database($o, $db);

echo 'Test passed: '.$nb_passed.'/'.$nb_test."\n";
if ($nb_passed != $nb_test){
    $warning = '# Warning: '.($nb_test-$nb_passed).' test(s) FAILED #';
    $line = str_repeat('#', strlen($warning));
    echo "--------\n".$line."\n";
    echo $warning."\n";
    echo $line."\n";
}
