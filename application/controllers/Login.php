<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Login extends CI_Controller {

    function __construct() {
        parent::__construct();

        $this->load->helper(array('form', 'url'));

        $this->load->library('form_validation');

        $this->load->helper('security');

        $this->load->library('tank_auth');

        $this->lang->load('tank_auth');
    }

    function index() {
//    if ($message = $this->session->flashdata('message')) {
//      $this->load->view('auth/general_message', array('message' => $message));
//    } else {
//      redirect('/auth/login/');
//    }
        $data['SITE'] = $this->config->item('SITE');

        $data['DEVELOPER'] = $this->config->item('DEVELOPER');

        $data['title'] = $data['SITE']['name'] . ' | ' . "Login Please";



        $data['login_theme_asset_url'] = base_url() . $this->config->item('ASSET_FOLDER') . $this->config->item('LOGIN_THEME');

//        login
        if ($this->tank_auth->is_logged_in()) {         // logged in
            redirect('admin');
        } elseif ($this->tank_auth->is_logged_in(FALSE)) {      // logged in, not activated
            redirect('/auth/send_again/');
        } else {

            $data['login_by_username'] = ($this->config->item('login_by_username', 'tank_auth') AND

                    $this->config->item('use_username', 'tank_auth'));

            $data['login_by_email'] = $this->config->item('login_by_email', 'tank_auth');



            $this->form_validation->set_rules('login', 'Login', 'trim|required|xss_clean');

            $this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean');

            $this->form_validation->set_rules('remember', 'Remember me', 'integer');



            // Get login for counting attempts to login

            if ($this->config->item('login_count_attempts', 'tank_auth') AND ( $login = $this->input->post('login'))) {

                $login = $this->security->xss_clean($login);
            } else {

                $login = '';
            }



            $data['use_recaptcha'] = $this->config->item('use_recaptcha', 'tank_auth');

            if ($this->tank_auth->is_max_login_attempts_exceeded($login)) {

                if ($data['use_recaptcha'])
                    $this->form_validation->set_rules('recaptcha_response_field', 'Confirmation Code', 'trim|xss_clean|required|callback__check_recaptcha');
                else
                    $this->form_validation->set_rules('captcha', 'Confirmation Code', 'trim|xss_clean|required|callback__check_captcha');
            }

            $data['errors'] = array();



            if ($this->form_validation->run()) {        // validation ok
                if ($this->tank_auth->login(
                                $this->form_validation->set_value('login'), $this->form_validation->set_value('password'), $this->form_validation->set_value('remember'), $data['login_by_username'], $data['login_by_email'])) {        // success
                    redirect('');
                } else {

                    $errors = $this->tank_auth->get_error_message();

                    if (isset($errors['banned'])) {        // banned user
                        $this->_show_message($this->lang->line('auth_message_banned') . ' ' . $errors['banned']);
                    } elseif (isset($errors['not_activated'])) {    // not activated user
                        redirect('/auth/send_again/');
                    } else {             // fail
                        foreach ($errors as $k => $v)
                            $data['errors'][$k] = $this->lang->line($v);
                    }
                }
            }

            $data['show_captcha'] = FALSE;

            if ($this->tank_auth->is_max_login_attempts_exceeded($login)) {

                $data['show_captcha'] = TRUE;

                if ($data['use_recaptcha']) {

                    $data['recaptcha_html'] = $this->_create_recaptcha();
                } else {

                    $data['captcha_html'] = $this->_create_captcha();
                }
            }
            ///

            $this->load->view($this->config->item('LOGIN_THEME') . "index", $data);
        }
    }

    /**
     * Logout user
     *
     * @return void
     */
    function logout() {

        $this->tank_auth->logout();



        $this->_show_message($this->lang->line('auth_message_logged_out'));
    }


    

    /**
     * Show info message
     *
     * @param string
     * @return  void
     */
    function _show_message($message) {
        $this->session->set_flashdata('message', $message);
        redirect('/auth/');
    }

   
    /**

     * Create CAPTCHA image to verify user as a human

     *

     * @return	string

     */
    function _create_captcha() {

        $this->load->helper('captcha');



        $cap = create_captcha(array(
            'img_path' => './' . $this->config->item('captcha_path', 'tank_auth'),
            'img_url' => base_url() . $this->config->item('captcha_path', 'tank_auth'),
            'font_path' => './' . $this->config->item('captcha_fonts_path', 'tank_auth'),
            'font_size' => $this->config->item('captcha_font_size', 'tank_auth'),
            'img_width' => $this->config->item('captcha_width', 'tank_auth'),
            'img_height' => $this->config->item('captcha_height', 'tank_auth'),
            'show_grid' => $this->config->item('captcha_grid', 'tank_auth'),
            'expiration' => $this->config->item('captcha_expire', 'tank_auth'),
        ));



        // Save captcha params in session

        $this->session->set_flashdata(array(
            'captcha_word' => $cap['word'],
            'captcha_time' => $cap['time'],
        ));



        return $cap['image'];
    }

    /**

     * Callback function. Check if CAPTCHA test is passed.

     *

     * @param	string

     * @return	bool

     */
    function _check_captcha($code) {

        $time = $this->session->flashdata('captcha_time');

        $word = $this->session->flashdata('captcha_word');



        list($usec, $sec) = explode(" ", microtime());

        $now = ((float) $usec + (float) $sec);



        if ($now - $time > $this->config->item('captcha_expire', 'tank_auth')) {

            $this->form_validation->set_message('_check_captcha', $this->lang->line('auth_captcha_expired'));

            return FALSE;
        } elseif (($this->config->item('captcha_case_sensitive', 'tank_auth') AND

                $code != $word) OR

                strtolower($code) != strtolower($word)) {

            $this->form_validation->set_message('_check_captcha', $this->lang->line('auth_incorrect_captcha'));

            return FALSE;
        }

        return TRUE;
    }

    /**

     * Create reCAPTCHA JS and non-JS HTML to verify user as a human

     *

     * @return	string

     */
    function _create_recaptcha() {

        $this->load->helper('recaptcha');



        // Add custom theme so we can get only image

        $options = "<script>var RecaptchaOptions = {theme: 'custom', custom_theme_widget: 'recaptcha_widget'};</script>\n";



        // Get reCAPTCHA JS and non-JS HTML

        $html = recaptcha_get_html($this->config->item('recaptcha_public_key', 'tank_auth'));



        return $options . $html;
    }

    /**

     * Callback function. Check if reCAPTCHA test is passed.

     *

     * @return	bool

     */
    function _check_recaptcha() {

        $this->load->helper('recaptcha');



        $resp = recaptcha_check_answer($this->config->item('recaptcha_private_key', 'tank_auth'), $_SERVER['REMOTE_ADDR'], $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);



        if (!$resp->is_valid) {

            $this->form_validation->set_message('_check_recaptcha', $this->lang->line('auth_incorrect_captcha'));

            return FALSE;
        }

        return TRUE;
    }

}

/* End of file auth.php */
/* Location: ./application/controllers/Auth.php */