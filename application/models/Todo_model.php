<?
class Todo_model extends CI_Model {

    private $salt = 'sflrt49fhi2asfa';

    function __construct(){
        parent::__construct();
        $this->load->database();
    }

    public function getList($type,$id){
        if($type == 'guid'){
            $this->db->where('guid',$id);
        }elseif($type == 'token'){
            $uid = $this->getUidByToken($id);
            $this->db->where('uid',$uid->id);
        }else{
            return false;
        }

        $query = $this->db->get('items',100);
        return $query->result();
    }
    public function getUidByToken($token){
        $this->db->select('id');
        $this->db->where('token',$token);
        $query = $this->db->get('users');
        return $query->row();
    }

    public function createItem($item_data){
        $this->db->insert("items", $item_data);
        $insertId = $this->db->insert_id();
        if($insertId){
            return $insertId;
        }else{
            return false;
        }
    }

    public function getOwnerById($id){
        $this->db->select('uid,guid');
        $this->db->where('id',$id);
        $query = $this->db->get('items');
        return $query->row();
    }

    public function changeStatus($id){
        $this->db->select('completed');
        $this->db->where('id',$id);
        $cur_status = $this->db->get('items')->row();
        if($cur_status->completed == 1){
            $data = array('completed'=>0);
        }else{
            $data = array('completed'=>1);
        }
        $this->db->where('id', $id);
        $this->db->update('items', $data);
    }

    public function deleteItem($id){
        $this->db->where('id',$id);
        $this->db->delete('items');
        if($this->db->affected_rows()>0){
            return true;
        }else{
            return false;
        }
    }

    public function deleteAll($owner,$owner_type){
        if($owner_type == 'token'){
            $this->db->where('uid',$owner);
        }else{
            $this->db->where('guid',$owner);
        }
        $this->db->where('completed','1');
        $this->db->delete('items');
        if($this->db->affected_rows()>0){
            return true;
        }else{
            return false;
        }
    }

    public function updateItem($id,$text){
        $data = array('text'=>$text);
        $this->db->where('id', $id);
        $this->db->update('items', $data);
        if($this->db->affected_rows()>0){
            return true;
        }else{
            return false;
        }
    }

}