<?php

namespace MeiliSearch;

/*
 Quick usage
 ===========
 $meili = Client('http://127.0.0.1:7700','my_api_key_can_be_blank_for_test');
 $product_index = $meili['product'];

 // Populate database
 $product_index->create();
 $product_index->add_documents([['name':'product1','description':'description1','saleable':1],
                                ['name':'product2','description':'description2','saleable':0]]);

 // Search
 $product_index->query()->filter('saleable=1')->search('duct2');
 */

class Exception extends \Exception{
    function __construct($http_code, $msg){
        parent::__construct($msg);
        $this->http_code = $http_code;
    }
}

class JsonRPC{
    protected $_headers = ['Content-Type: application/json'];
    
    function __construct($base_url){
        $this->_base_url = $base_url;
    }
    
    protected function add_header($header){
        $this->_headers[] = $header;
    }
    
    protected function rpc($method, $url, $data=null){
        // $method: PUT POST DELETE
        $curl = curl_init();
        switch ($method){
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                    break;
            case "PUT":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                    break;
            case "DELETE":
            case "PUT":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }
        // OPTIONS:
        curl_setopt($curl, CURLOPT_URL, $this->_base_url.$url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->_headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $result = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        switch($http_code){
            case 200: // OK
            case 201: // Created: The resource has been created (synchronous)
            case 202: // Accepted: The update has been pushed in the update queue (asynchronous)
            case 204: // No Content: The resource has been deleted or no content has been returned
            case 205: //Reset Content: All the resources have been deleted
                return json_decode($result);
            case 400: //Bad Request: The request was unacceptable, often due to missing a required parameter.
            case 401: //Unauthorized: No valid API key provided.
            case 403: //Forbidden: The API key doesn't have the permissions to perform the request.
            case 404: //Not Found: The requested resource doesn't exist.
                $res = json_decode($result);
                throw new Exception($http_code, $res->message);
            default:
                throw new Exception($http_code, 'Unknown HTTP response code ['.$http_code.']');
        }
    }
}

class RPC extends JsonRPC{
    function __construct($base_url, $api_key=null){
        parent::__construct($base_url);
        if (!is_null($api_key)) $this->add_header('X-Meili-API-Key: '.$api_key);
    }
    
    public function list_indexes(){
        //List all indexes.
        return $this->rpc('GET', '/indexes');
    }
    
    public function get_index($index_uid){
        //Get information about an index
        return $this->rpc('GET', '/indexes/'.$index_uid);
    }
    
    public function create_index($index_uid, $primary_key=null){
        //Create an index
        $data = ['uid'=>$index_uid];
        if (!is_null($primary_key)) $data['primaryKey'] = $primary_key;
        return $this->rpc('POST', '/indexes', $data);
    }
    
    public function update_index($index_uid, $primary_key){
        //Update an index
        return $this->rpc('PUT', '/indexes/'.$index_uid, ['primaryKey'=>$primary_key]);
    }
    
    public function delete_index($index_uid){
        //Delete an index
        return $this->rpc('DELETE', '/indexes/'.$index_uid);
    }

    public function get_document($index_uid, $document_id){
        // Get one document using its unique id
        return $this->rpc('GET', '/indexes/'.$index_uid.'/documents/'.$document_id);
    }

    public function add_document($index_uid, $documents){
        // Add or replace documents if their key exists
        return $this->rpc('POST', '/indexes/'.$index_uid.'/documents', $documents);
    }

    public function update_document($index_uid, $documents){
        // Add or update documents (only change attribute, do not delete)
        return $this->rpc('PUT', '/indexes/'.$index_uid.'/documents', $documents);
    }

    public function delete_all_documents($index_uid){
        // Delete all documents
        return $this->rpc('DELETE', '/indexes/'.$index_uid.'/documents');
    }

    public function delete_document($index_uid, $document_id){
        // Delete one document based on its unique id.
        return $this->rpc('DELETE', '/indexes/'.$index_uid.'/documents/'.$document_id);
    }

    public function delete_documents($index_uid, $document_ids){
        // Delete a selection of documents based on array of document id's.
        return $this->rpc('POST', '/indexes/'.$index_uid.'/documents/delete-batch', $document_ids);
    }

    public function search($index_uid, $query, $parameters=null){
        // Search
        // Parameters array:
        // offset: 0 Number of documents to skip
        // limit: 20 Maximum number of documents returned
        // attributesToRetrieve: '*' Attributes to display in the returned documents (ie: name,date)
        // attributesToCrop: null Attributes whose values have to be cropped (ie: name,date) or (ie: name:20,date) or (ie: *) or (ie: *,name:20,title:0)
        // cropLength: 200 Defautlt length used to crop field values
        // attributesToHighlight: null Attributes whose values will contain highlighted matching terms (ie: overview,title) or (ie: *)
        // filters: null Filter queries by an attribute value (ie: rating >= 3 AND (NOT director = "Tim Burton") ) (ie: director = "Jordan Peele")
        // matches: false Defines whether an object that contains information about the matches should be returned or not (This is useful when you need to highlight the results without the default HTML highlighter.)

        if (is_null($parameters)) $parameters = [];
        $data = array_merge(['q'=>$query], $parameters);
        return $this->rpc('GET', '/indexes/'.$index_uid.'/search', $data);
    }

    public function get_settings($index_uid){
        // Get the settings of an index
        return $this->rpc('GET', '/indexes/'.$index_uid.'/settings');
    }

    public function update_settings($index_uid, $new_settings){
        //Update the settings of an index.
        //Updates in the settings route are partial. This means that any parameters not provided will be left unchanged.
        // $new_settings = array
        // synonyms: Object {}: List of associated words treated similarly
        // stopWords: [Strings] []: List of words ignored by MeiliSearch when present in search queries
        // rankingRules: [Strings] : List of ranking rules sorted by order of importance
        // distinctAttribute: String null: Search returns documents with distinct (different) values of the given field
        // searchableAttributes: [Strings] : Fields in which to search for matching query words sorted by order of importance
        // displayedAttributes: [Strings] : Fields displayed in the returned documents
        // acceptNewFields: Boolean True: Defines whether new fields should be searchable and displayed or not
        if (count($new_settings) == 0) return;
        return $this->rpc('POST', '/indexes/'.$index_uid.'/settings', $new_settings);
    }

    public function reset_settings($index_uid){
        //Reset the settings of an index. All settings will be reset to their default value.
        return $this->rpc('DELETE', '/indexes/'.$index_uid.'/settings');
    }
}

class Query{
    protected $_index_uid;
    protected $_rpc;
    protected $_parameters;

    function __construct($rpc, $index_uid){
        $this->_index_uid = $index_uid;
        $this->_rpc = $rpc;
        $this->_parameters = [];
    }

    public function search($query){
        return $this->_rpc->search($this->_index_uid, $query, $this->_parameters);
    }

    public function offset($offset=0){
        $this->_parameters['offset'] = (int)$offset;
        return $this;
    }

    public function limit($limit=20){
        $limit = (int)$limit;
        if ($limit != 0) $this->_parameters['limit'] = $limit;
        return $this;
    }

    public function retrieve($attributes){
        // name,date
        $this->_parameters['attributesToRetrieve'] = $attributes;
        return $this;
    }

    public function crop($attributes){
        // name,date
        // name:20,date
        // *
        // *,name:20,title:0
        $this->_parameters['attributesToCrop'] = $attributes;
        return $this;
    }

    public function default_crop_length($length=200){
        $this->_parameters['cropLength'] = $length;
        return $this;
    }

    public function highlight($attributes){
        // overview,title
        // *
        $this->_parameters['attributesToHighlight'] = $attributes;
        return $this;
    }

    public function filter($filter){
        // rating >= 3 AND (NOT director = "Tim Burton") 
        // director = "Jordan Peele"
        $this->_parameters['filter'] = $filter;
        return $this;
    }

    public function match_info($match_info=false){
        $this->_parameters['matches'] = $match_info;
        return $this;
    }
}

class Index{
    protected $_index_uid;
    protected $_rpc;
    protected $_exists;
    protected $_primary_key;

    function __construct($rpc, $index_uid){
        $this->_init();
        $this->_rpc = &$rpc;
        $this->_index_uid = $index_uid;
        try{
            $info = $this->_rpc->get_index($index_uid);
            $this->_exists = true;
            $this->_primary_key = $info->primaryKey;
        } catch(Exception $e){
            if ($e->http_code != 404) throw $e;
        }
    }
    
    protected function _init(){
        $this->_exists = false;
        $this->_primary_key = null;
    }

    public function create($primary_key=null){
        if ($this->_exists){
            if ($primary_key && $primary_key != $this->_primary_key) $this->update($primary_key);
        }else{
            $this->_primary_key = $primary_key;
            $this->_rpc->create_index($this->_index_uid, $primary_key);
        }
    }

    public function update($primary_key){
        $this->_primary_key = $primary_key;
        $this->_rpc->update_index($this->_index_uid, $primary_key);
    }

    public function delete(){
        //delete the index
        if (!$this->_exists) return;
        $this->_rpc->delete_index($this->_index_uid);
        $this->_init();
    }

    public function add_documents($documents){
        //Add or replacedocument if key exists
        $this->_rpc->add_document($this->_index_uid, $documents);
    }

    public function update_documents($documents){
        //Add or update documents (only change attribute, do not delete)
        $this->_rpc->update_document($this->_index_uid, $documents);
    }

    public function delete_all_documents(){
        // Delete all documents
        $this->_rpc->delete_all_documents($this->_index_uid);
    }
    
    public function delete_document($document_id){
        // Delete one document based on its unique id.
        $this->_rpc->delete_document($this->_index_uid, $document_id);
    }
    
    public function delete_documents($document_ids){
        //Delete a selection of documents based on array of document id's.
        $this->_rpc->delete_documents($this->_index_uid, $document_ids);
    }

    public function query(){
        return new Query($this->_rpc, $this->_index_uid);
    }

    public function get_settings(){
        // Get the settings of an index
        return $this->_rpc->get_settings($this->_index_uid);
    }
    
    public function update_settings($new_settings){
        //Update the settings of an index.
        //Updates in the settings route are partial. This means that any parameters not provided will be left unchanged.
        // $new_settings = array
        // synonyms: Object {}: List of associated words treated similarly
        // stopWords: [Strings] []: List of words ignored by MeiliSearch when present in search queries
        // rankingRules: [Strings] : List of ranking rules sorted by order of importance
        // distinctAttribute: String null: Search returns documents with distinct (different) values of the given field
        // searchableAttributes: [Strings] : Fields in which to search for matching query words sorted by order of importance
        // displayedAttributes: [Strings] : Fields displayed in the returned documents
        // acceptNewFields: Boolean True: Defines whether new fields should be searchable and displayed or not
        return $this->_rpc->update_settings($this->_index_uid, $new_settings);
    }
    
    public function reset_settings(){
        //Reset the settings of an index. All settings will be reset to their default value.
        return $this->_rpc->reset_settings($this->_index_uid);
    }
}

class Client{
    function __construct($base_url, $api_key=null){
        $this->_rpc = new RPC($base_url, $api_key);
    }

    public function list_indexes(){
        return $this->_rpc->list_indexes();
    }

    public function __get($index_uid){
        return new Index($this->_rpc, $index_uid);
    }

    public function index($index_uid){
        return new Index($this->_rpc, $index_uid);
    }
}
