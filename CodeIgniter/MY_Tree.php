<?php
class MY_Tree extends MY_Model {
    
    //if you rename your table fields, also rename them here
    protected $parent_id = 'parent_id';

    protected $lineage = 'lineage';

    protected $deep = 'deep';   
    
    public function __construct($config = null)
    {
        parent::__construct();
        
        if(is_array($config)) $this->initialize($config);
    }
    
    public function initialize($config){
        if(!is_array($config)) return false;
        
        foreach($config as $key => $val){
            $this->$key = $val;
        }
    }           
    
    // Fetch all records based on the primary key, ordered by their lineage. 
    // param - integer - allows you to return only from a certain point  (optional)
    // Returns result_array 
    public function get_nodes($top_id=0)
    {
        if (!empty($top_id))
        {
            $parent = $this->get($top_id);
            if (!empty($parent))
            {
                $this->like($this->lineage, $parent->{$this->lineage}, 'after');
            }
        }
        $query = $this->order_by($this->lineage)->get_all();
        return $query;        
    }
    
    // Fetch all direct child records based on the parent id, ordered by their lineage. 
    // param - integer - parent id of child records
    // Returns result_array 
    public function get_children($parent_id)
    {       
        $query = $this->order_by($this->lineage)->get_many_by($this->parent_id, $parent_id);
        return $query; 
    }
    
    // Fetch all descendent records based on the parent id, ordered by their lineage. 
    // param - integer - parent id of descendent records
    // Returns result_array 
    public function get_descendents($parent_id)
    {       
        $parent = $this->get($parent_id);
        if (empty($parent)) return array();

        // note that adding '-' to the like leaves out the parent record
        $query = $this->order_by($this->lineage)->like($this->lineage, $parent->{$this->lineage}.'-', 'after')->get_all();
        return $query; 
    }
    
    // Fetch all ancestor records based on the id, ordered by their lineage (top to bottom). 
    // param - integer - id of descendent record
    // Returns result_array 
    public function get_ancestors($id, $remove_this = false)
    {       
        $current = $this->get($id);
        if (empty($current)) return array();

        $lineage_ids = explode('-' , $current->{$this->lineage});

        if ($remove_this) unset($lineage_ids[count($lineage_ids)-1]);

        $query = $this->order_by($this->lineage)->get_many($lineage_ids);
        return $query; 
    }
    
    // Fetch parent of record based on the id 
    // param - integer - id of descendent record
    // Returns row 
    public function get_parent($id)
    {       
        $current = $this->get($id);
        if (empty($current)) return array();
       
        $query = $this->get($current->{$this->parent_id});
        return $query; 
    }
    
    // Fetch all descendent records based on the parent id, ordered by their lineage, and groups them as a mulit-dimensional array. 
    // param - integer - parent id of descendent records (optional)
    // Returns result_array 
    public function get_grouped_children($top_id=0)
    {
        $result = $this->get_nodes($top_id);
        $grouped_result = $this->_findChildren($result);
        return $grouped_result;
    }
    
    // inserts new record. If no parent_id included, assumes top level item
    // returns result of final statement
    public function insert_node($data)
    {
        if(!empty($data[$this->parent_id]))
        {
            //get parent info
            $parent = $this->get($data[$this->parent_id]);
            $data[$this->deep] = $parent->{$this->deep} + 1;
        }   

        $insert_id = $this->insert($data);

        //update new record's lineage
        $update[$this->lineage] = (empty($parent->{$this->lineage}))? str_pad($insert_id, 5 ,'0', STR_PAD_LEFT): $parent->{$this->lineage}.'-'.str_pad($insert_id, 5, '0', STR_PAD_LEFT);

        return $this->update($insert_id, $update);
    }
    
    // deletes record
    // param - true/false - delete all descendent records
    public function delete_node($id, $with_children=false)
    {
        //little clumsy, due to some Active Record restrictions

        if ($with_children)
        {
            $parent = $this->get($id);
        }   

        $this->like($this->primary_key, $id, 'none');
        if (!empty($parent) && $with_children)
        {
            $this->or_like($this->lineage, $parent->{$this->lineage}.'-', 'after');
        }   
        
        $result = $this->get_all();
        
        foreach($result as $val)
        {
            $delete_keys[] = $val->{$this->primary_key};
        }
        
        $this->delete_many($delete_keys);
    }
    
    //for use when the data is existing & has parent_id, but no lineage or deep
    //can be used to repair your data or set it up the first time
    function resync()
    {
        //we could probably just re-write this with two copies of your table, and update. I think this will run safer and leave less to worry
        $current_data = $this->order_by($this->parent_id)->get_all();
        if (!empty($current_data))
        {
            foreach ($current_data as $row) {
                
                foreach($row as $k => $v){
                    $update[$k] = $v;
                }
                
                $update[$this->deep] = 0;

                if(!empty($row->{$this->parent_id}))
                {
                    //get parent info
                    $parent = $this->get($row->{$this->parent_id});
                    $update[$this->deep] = $parent->{$this->deep} + 1;
                }                   

                $update[$this->lineage] = (empty($parent->{$this->lineage}))? str_pad($row->{$this->primary_key}, 5 ,'0', STR_PAD_LEFT): $parent->{$this->lineage}.'-'.str_pad($row->{$this->primary_key}, 5, '0', STR_PAD_LEFT);
                
                $this->update($row->{$this->primary_key}, $update);
                unset($parent);
            }
        }
    }
    
    // gets the maximum depth of any branch of tree
    // returns integer
    public function max_deep()
    {
        $this->db->select_max($this->deep, 'max_deep');
        $row = $this->get_all();
        return $row[0]->max_deep + 1; //deep starts at 0
    }
    
    function _findChildren(&$nodeList, $parentId = 0) {
        $nodes = array();

        foreach ($nodeList as $node) {
            if ($node->{$this->parent_id} == $parentId) {
                $node->children = $this->_findChildren($nodeList, $node->{$this->primary_key});
                $nodes[] = $node;
            }
        }

        return $nodes;
    }
    
    /**
    * A wrapper to $this->_database->like()
    */
    public function like($field, $value, $wildcard = 'after')
    {
        $this->_database->like($field, $value, $wildcard);
        return $this;
    }
    
    /**
    * A wrapper to $this->_database->or_like()
    */
    public function or_like($field, $value, $wildcard = 'after')
    {
        $this->_database->or_like($field, $value, $wildcard);
        return $this;
    }
}