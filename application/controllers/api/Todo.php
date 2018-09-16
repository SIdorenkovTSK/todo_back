<?php
use Restserver\Libraries\REST_Controller;
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/Format.php';


class Todo extends REST_Controller {

    function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin: ' . $this->config->item('allow_addr'));
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        //Необходимо чтобы работали CORS запросы
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == "OPTIONS") {
            die();
        }
        $this->load->library(array('ion_auth', 'form_validation'));
        $this->load->helper(array('url', 'language'));
        $this->form_validation->set_error_delimiters($this->config->item('error_start_delimiter', 'ion_auth'), $this->config->item('error_end_delimiter', 'ion_auth'));
        $this->lang->load('auth');
        $this->load->model('todo_model');
    }

    /*Получение\выдача данных*/
    public function item_get()
    {

        $id = $this->get('id');
        $type = $this->get('type');
        if($type == 'token' && !$this->checkToken($this->get('guid'))){
            $this->response('invalid_token', REST_Controller::HTTP_BAD_REQUEST);
        }

        $items = $this->todo_model->getList($type,$id);
        $this->set_response($items, REST_Controller::HTTP_OK);
    }

    public function item_post()
    {
        if($this->post('id_type') == 'token'){
            if($this->checkToken($this->post('guid'))){
                $uid = $this->todo_model->getUidByToken($this->post('guid'));
                $item_info = [
                    'uid' =>$uid->id,
                    'text' => $this->post('text'),
                ];
            }else{
                $this->response('invalid_token', REST_Controller::HTTP_BAD_REQUEST);
            }

        }else{
            $item_info = [
                'guid' => $this->post('guid'),
                'text' => $this->post('text'),
            ];
        }
        $this->createItem($item_info);
    }

    public function item_put()
    {
        if($this->put('id_type') == 'token') {
            if($this->checkToken($this->put('guid'))) {
                $uid = $this->todo_model->getUidByToken($this->put('guid'));
                $owner = $uid->id;
            }else{
                $this->response('invalid_token', REST_Controller::HTTP_BAD_REQUEST);
            }

        }else{
            $owner = $this->put('guid');
        }
        $operation = $this->put('operation');
        if($operation == 'change_status'){
            $id = $this->put('id');
            $owner = $owner;
            $this->changeStatus($id,$owner);
        }elseif($operation == 'update'){
            $item_info = [
                'owner' => $owner,
                'text' => $this->put('text'),
                'id' => $this->put('id'),
            ];
            $this->updateItem($item_info);
        }

    }

    public function item_delete()
    {

        $id = $this->delete('id');
        if($this->delete('id_type') == 'token') {
            if($this->checkToken($this->delete('guid'))) {
                $uid =  $this->todo_model->getUidByToken($this->delete('guid'));
                $owner = $uid->id;
            }else{
                $this->response('invalid_token', REST_Controller::HTTP_BAD_REQUEST);
            }
        }else{
            $owner = $this->delete('guid');
        }

        if($id == 'all'){
            if($this->todo_model->deleteAll($owner,$this->delete('id_type'))){
                if(isset($owner_info->uid)  && $owner_info->uid!=0){
                    $item_info['new_token'] = $this->updateToken($owner_info->uid);
                }
                $item_info['id'] = $id;
                $this->set_response($item_info, REST_Controller::HTTP_OK);
            }else{
                $this->set_response(NULL, REST_Controller::HTTP_BAD_REQUEST);
            }
        }else{
            $owner_info = $this->todo_model->getOwnerById($id);
            if($owner_info->uid == $owner || $owner_info->guid == $owner){
                if($this->todo_model->deleteItem($id)){
                    if(isset($owner_info->uid)  && $owner_info->uid!=0){
                        $item_info['new_token'] = $this->updateToken($owner_info->uid);
                    }
                    $item_info['id'] = $id;
                    $this->set_response($item_info, REST_Controller::HTTP_OK);
                }else{
                    $this->set_response(NULL, REST_Controller::HTTP_BAD_REQUEST);
                }
            }else{
                $this->response(NULL, REST_Controller::HTTP_BAD_REQUEST);
            }
        }

    }

    /*Операции над записями*/
    private function createItem($item_info){

        $id = $this->todo_model->createItem($item_info);
        $item_info['id'] = $id;
        if(isset($item_info['uid'])){
            $item_info['new_token'] = $this->updateToken($item_info['uid']);
        }
        if($id){
            $this->set_response($item_info, REST_Controller::HTTP_CREATED);
        }else{
            $this->set_response(NULL, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    private function updateItem($item_info){
        $id = $item_info['id'];
        $owner = $item_info['owner'];
        $text = $item_info['text'];
        $owner_info = $this->todo_model->getOwnerById($id);
        if(isset($owner_info->uid) && $owner_info->uid!=0){
            $item_info['new_token'] = $this->updateToken($owner_info->uid);
        }
        if($owner_info->uid == $owner || $owner_info->guid == $owner){
            if( $this->todo_model->updateItem($id,$text)){
                $this->set_response($item_info, REST_Controller::HTTP_OK);
            }else{
                $this->set_response(NULL, REST_Controller::HTTP_BAD_REQUEST);
            }
        }else{
            $this->response(NULL, REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    private function changeStatus($id,$owner){
        $owner_info = $this->todo_model->getOwnerById($id);
        $item_info = array();
        if(isset($owner_info->uid) && $owner_info->uid!=0){
            $item_info['new_token'] = $this->updateToken($owner_info->uid);
        }
        if($owner_info->uid == $owner || $owner_info->guid == $owner){
            $this->todo_model->changeStatus($id);
            $item_info['id'] = $id;
            $this->set_response($item_info, REST_Controller::HTTP_OK);
        }else{
            $this->response(NULL, REST_Controller::HTTP_BAD_REQUEST);
        }
    }
    /*Авторизация\регистрация*/

    public function user_post(){
        $tables = $this->config->item('tables', 'ion_auth');
        $this->form_validation->set_rules('email', $this->lang->line('create_user_validation_email_label'), 'trim|required|valid_email|is_unique[' . $tables['users'] . '.email]');
        $this->form_validation->set_rules('password', $this->lang->line('create_user_validation_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']');

        if ($this->form_validation->run() === TRUE)
        {
            $identity = $this->input->post('email');
            $password = $this->input->post('password');
            $additional_data = array(
                'token' => $this->generateToken(),
            );
        }
        if ($this->form_validation->run() === TRUE && $this->ion_auth->register($identity, $password,$identity,$additional_data))
        {
            $answer['status'] = 1;
            $answer['message'] = $this->ion_auth->messages();
            $answer['token'] = $additional_data['token'];
            $answer['login'] = $identity;
            $this->set_response($answer, REST_Controller::HTTP_OK);
        }
        else
        {
            $answer['status'] = 0;
            $answer['message'] = (validation_errors() ? validation_errors() : ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));
            $this->set_response( $answer, REST_Controller::HTTP_OK);
        }
    }



    public function login_post()
    {

        $tables = $this->config->item('tables', 'ion_auth');
        $this->form_validation->set_rules('email', str_replace(':', '', $this->lang->line('login_identity_label')), 'required');
        $this->form_validation->set_rules('password', str_replace(':', '', $this->lang->line('login_password_label')), 'required');

        if ($this->form_validation->run() === TRUE)
        {

            if ($this->ion_auth->login($this->input->post('email'), $this->input->post('password')))
            {
                $user = $this->ion_auth->user()->row();
                $token = $this->generateToken();
                $this->ion_auth->update($user->id, array('token'=>$token));
                $answer['status'] = 1;
                $answer['message'] = $this->ion_auth->messages();
                $answer['token'] = $token;
                $answer['login'] = $this->input->post('email');
                $this->set_response($answer, REST_Controller::HTTP_OK);
            }
            else
            {
                $answer['status'] = 0;
                $answer['message'] = $this->ion_auth->errors();
                $this->set_response( $answer, REST_Controller::HTTP_OK);
            }
        }
        else
        {
            $answer['status'] = 0;
            $answer['message'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('message');
            $this->set_response( $answer, REST_Controller::HTTP_OK);
        }
    }

    //Токен генерируем для каждой операции (кроме GET)
    private function updateToken($uid){
        $token = $this->generateToken();
        $this->ion_auth->update($uid, array('token'=>$token));
        return $token;
    }

    private function generateToken()
    {
        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }

    private function checkToken($token){
        $uid = $this->todo_model->getUidByToken($token);
        if(isset($uid)){
            return true;
        }else{
           return false;
        }
    }

}
