<?php
/**
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2017 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

include(_PS_MODULE_DIR_ . 'payco/lib/CreditCard_Order.php');
include(_PS_MODULE_DIR_ . 'payco/lib/CreditCard_OrderState.php');


class Payco extends PaymentModule
{
    protected $config_form = false;

    private $_html = '';
    private $_postErrors = array();
    public $orderStates;
    public $p_cust_id_cliente;
    public $p_key;
    public $public_key;
    public $p_test_request;
    public $p_type_checkout;
    public $p_url_response;
    public $p_url_confirmation;
    public $p_state_end_transaction;

    public function __construct()
    {
       
        $this->name = 'payco';
        $this->author = 'epayco';
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->tab = 'payments_gateways';
        $this->controllers = array('payment', 'validation');
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;


        $config = Configuration::getMultiple(array('P_CUST_ID_CLIENTE',
                                                'P_KEY','PUBLIC_KEY',
                                                'P_TEST_REQUEST',
                                                'P_TITULO',
                                                'P_URL_RESPONSE',
                                                'P_TYPE_CHECKOUT',
                                                'P_URL_CONFIRMATION',
                                                'P_STATE_END_TRANSACTION'));

        if (isset($config['P_CUST_ID_CLIENTE']))
            $this->p_cust_id_cliente = trim($config['P_CUST_ID_CLIENTE']);
        if (isset($config['P_KEY']))
            $this->p_key = trim($config['P_KEY']);
        if (isset($config['PUBLIC_KEY']))
            $this->public_key = trim($config['PUBLIC_KEY']);  
        if (isset($config['P_TEST_REQUEST']))
            $this->p_test_request = $config['P_TEST_REQUEST'];
        if (isset($config['P_TITULO']))
            $this->p_titulo = trim($config['P_TITULO']);
        if (isset($config['P_URL_RESPONSE']))
            $this->p_url_response = trim($config['P_URL_RESPONSE']);
        if (isset($config['P_URL_CONFIRMATION']))
            $this->p_url_confirmation = trim($config['P_URL_CONFIRMATION']);  
        if (isset($config['P_TYPE_CHECKOUT']))
            $this->p_type_checkout = $config['P_TYPE_CHECKOUT'];
        if (isset($config['P_STATE_END_TRANSACTION']))
            $this->p_state_end_transaction = $config['P_STATE_END_TRANSACTION'];
  

        parent::__construct();

        $this->version="2.0.0";
        $this->displayName = $this->l('payco');
        $this->description = $this->l('ePayco, Tarjetas de Credito, Debito PSE, SafetyPay y Efectivo');

        $this->confirmUninstall = $this->l('Esta seguro de desistalar este mmodulo?');

        //$this->limited_countries = array('FR');

        //$this->limited_currencies = array('EUR');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);


        if (!isset($this->p_cust_id_cliente) OR !isset($this->p_key) OR !isset($this->public_key))
        $this->warning = $this->l('P_CUST_ID_CLIENTE, P_KEY y PUBLIC_KEY deben estar configurados para utilizar este módulo correctamente');
        if (!sizeof(Currency::checkPaymentCurrencies($this->id)))
        $this->warning = $this->l('No currency set for this module');

    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        /*$iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (in_array($iso_code, $this->limited_countries) == false)
        {
            $this->_errors[] = $this->l('This module is not available in your country');
            return false;
        }
        */

        if (!isset($this->p_cust_id_cliente))
          Configuration::updateValue('P_CUST_ID_CLIENTE', '');
        if (!isset($this->p_key))
              Configuration::updateValue('P_KEY', '');
        if (!isset($this->public_key))
              Configuration::updateValue('PUBLIC_KEY', '');  
        if (!isset($this->p_test_request))
              Configuration::updateValue('P_TEST_REQUEST', false);
        if (!isset($this->p_titulo))
              Configuration::updateValue('P_TITULO', 'Checkout ePayco, (Tarjetas de crédito,debito,efectivo.');
        if (!isset($this->p_url_response))
              Configuration::updateValue('P_URL_RESPONSE', Context::getContext()->link->getModuleLink('payco', 'response'));
        if (!isset($this->p_url_confirmation))
              Configuration::updateValue('P_URL_CONFIRMATION', Context::getContext()->link->getModuleLink('payco', 'confirmation'));
        if (!isset($this->p_state_end_transaction))
              Configuration::updateValue('P_STATE_END_TRANSACTION', 'PS_OS_PAYMENT');
    
        //Set up our currencies and issuers
        CreditCard_OrderState::setup();
        //CreditCard_Issuer::setup();
        CreditCard_Order::setup();

        //Configuration::updateValue('PAYCO_LIVE_MODE', false);

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('payment') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('actionPaymentConfirmation') &&
            $this->registerHook('displayPaymentReturn') &&
            $this->registerHook('displayPaymentTop');
    }

    public function uninstall()
    {
        CreditCard_Order::remove();
        Configuration::deleteByName('PAYCO_LIVE_MODE');
        Configuration::deleteByName('P_TITULO');
        Configuration::deleteByName('P_CUST_ID_CLIENTE');
        Configuration::deleteByName('P_KEY');
        Configuration::deleteByName('PUBLIC_KEY');
        Configuration::deleteByName('P_TEST_REQUEST');
        Configuration::deleteByName('P_URL_RESPONSE');
        Configuration::deleteByName('P_URL_CONFIRMATION');
        Configuration::deleteByName('P_TYPE_CHECKOUT');
        Configuration::deleteByName('P_STATE_END_TRANSACTION');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    protected function _displayInfoAdmin()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */

         if (Tools::isSubmit('btnSubmit')) {
            $this->postValidation();
            if (!count($this->_postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->_displayInfoAdmin();
        $this->_html .= $this->renderForm();

        return $this->_html;
        
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
      
      $states = CreditCard_OrderState::getOrderStates();
      $id_os_initial = Configuration::get('PAYCO_ORDERSTATE_WAITING');
      
      $order_states=array();
      
      foreach($states as $state){
        $order_states[]=array("id"=>$state["id_order_state"],"name"=>$state["name"]);
      }

      $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Configuración ePayco', array(), 'Modules.Payco.Admin'),
                    'icon' => 'icon-envelope'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label'=> $this->trans('Titulo', array(), 'Modules.Payco.Admin'),
                        'name' => 'P_TITULO',
                        'required' => true,
                        'desc' => $this->trans('Titulo que el usuario vera durante el Checkout del Plugin', array(), 'Modules.Payco.Admin'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('P_CUST_ID_CLIENTE', array(), 'Modules.Payco.Admin'),
                        'name' => 'P_CUST_ID_CLIENTE',
                        'desc' => $this->trans('Id del cliente que lo identifica en ePayco.', array(), 'Modules.Payco.Admin'),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('P_KEY', array(), 'Modules.Payco.Admin'),
                        'name' => 'P_KEY',
                        'desc' => $this->trans('Llave para firmar la información enviada y recibida de ePayco', array(), 'Modules.Payco.Admin'),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('PUBLIC_KEY', array(), 'Modules.Payco.Admin'),
                        'name' => 'PUBLIC_KEY',
                        'desc' => $this->trans('LLave para autenticar y consumir los servicios de ePayco.', array(), 'Modules.Payco.Admin'),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Página de Respuesta', array(), 'Modules.Payco.Admin'),
                        'name' => 'P_URL_RESPONSE',
                        'placeholder'=>"http://tutienda.com/respuesta",
                        'desc' => $this->trans('Url de la tienda mostrada luego de finalizar el pago.', array(), 'Modules.Payco.Admin'),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Página de Confirmación', array(), 'Modules.Payco.Admin'),
                        'name' => 'P_URL_CONFIRMATION',
                        'placeholder'=>"http://tutienda.com/confirmacion",
                        'desc' => $this->trans('Url de Confirmación donde ePayco confirma el pago.', array(), 'Modules.Payco.Admin'),
                        'required' => true
                    ),
                    array(
                        'type' => 'radio',
                        'label'=> $this->trans('Habilitar modo pruebas', array(), 'Modules.Payment.Admin'),
                        'name' => "P_TEST_REQUEST",
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'P_TEST_REQUEST_TRUE',
                                'value' => true,
                                'label' => $this->trans('Si (Transacciones en pruebas)', array(), 'Modules.Payment.Admin'),
                            ),
                            array(
                                'id' => 'P_TEST_REQUEST_FALSE',
                                'value' => false,
                                'label' => $this->trans('No (Transacciones en producción)', array(), 'Modules.Payment.Admin'),
                            )
                        ),
                    ),
                    array(
                        'type' => 'radio',
                        'label'=> $this->trans('Tipo de checkout ePayco', array(), 'Modules.Payco.Admin'),
                        'name' => "P_TYPE_CHECKOUT",
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'onpage',
                                'value' => true,
                                'label' => $this->trans('OnPage Checkout (El usuario al pagar se queda en la tienda no hay redirección a ePayco)', array(), 'Modules.Payco.Admin'),
                            ),
                            array(
                                'id' => 'standart',
                                'value' => false,
                                'label' => $this->trans('Estandar Checkout (El usuario al pagar es redireccionado a la pasarela de ePayco)', array(), 'Modules.Payco.Admin'),
                            )
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->trans('Estado final Pedido', array(), 'Modules.Payco.Admin'),
                        'name' => 'P_STATE_END_TRANSACTION',
                        'desc' => $this->trans('Escoja el estado del pago que se aplicar al confirmar la trasacción.', array(), 'Modules.Payco.Admin'),
                        'required' => true,
                        'options' => array(
                              'id' => 'id',
                              'name' => 'name',
                              'default' => array(
                                  'value' => '',
                                  'label' => $this->l('Seleccione un estado de Orden')
                              ),
                              'query'=>$order_states,

                        ),
                    ),

                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );

        return $fields_form;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'P_TITULO' => Tools::getValue('P_TITULO', Configuration::get('P_TITULO')),
            'P_CUST_ID_CLIENTE' => Tools::getValue('P_CUST_ID_CLIENTE', Configuration::get('P_CUST_ID_CLIENTE')),
            'P_KEY' => Tools::getValue('P_KEY', Configuration::get('P_KEY')),
            'PUBLIC_KEY' => Tools::getValue('PUBLIC_KEY', Configuration::get('PUBLIC_KEY')),
            'P_TEST_REQUEST' => Tools::getValue('P_TEST_REQUEST', Configuration::get('P_TEST_REQUEST')),
            'P_TYPE_CHECKOUT' => Tools::getValue('P_TYPE_CHECKOUT', Configuration::get('P_TYPE_CHECKOUT')),
            'P_URL_RESPONSE' => Tools::getValue('P_URL_RESPONSE', Configuration::get('P_URL_RESPONSE')),
            'P_URL_CONFIRMATION' => Tools::getValue('P_URL_CONFIRMATION', Configuration::get('P_URL_CONFIRMATION')),
            'P_STATE_END_TRANSACTION'=>Tools::getValue('P_STATE_END_TRANSACTION', Configuration::get('P_STATE_END_TRANSACTION'))
        );
    }

    private function postValidation() {
      if (Tools::isSubmit('btnSubmit')) {
        if (!Tools::getValue('P_CUST_ID_CLIENTE'))
          $this->_postErrors[] = $this->l('\'P_CUST_ID_CLIENTE\' Campo Requerido.');
        if (!Tools::getValue('P_KEY'))
          $this->_postErrors[] = $this->l('\'P_KEY\' Campo Requerido.');
        if (!Tools::getValue('PUBLIC_KEY'))
          $this->_postErrors[] = $this->l('\'PUBLIC_KEY\' Campo Requerido.');      
        if (!Tools::getValue('P_TITULO'))
          $this->_postErrors[] = $this->l('\'P_TITULO\' Campo Requerido.');
        
      }
    }


    /**
     * Save form data.
     */
    protected function postProcess()
    {
            
          
            if (Tools::isSubmit('btnSubmit')) {
            //Setear url respuesta y confirmacion  

            if(Tools::getValue('P_URL_RESPONSE')=="")
            {
              $p_url_response=Context::getContext()->link->getModuleLink('payco', 'response');
            }else{
               $p_url_response=Tools::getValue('P_URL_RESPONSE');
            }
            if(Tools::getValue('P_URL_CONFIRMATION')=="")
            {
              $p_url_confirmation=Context::getContext()->link->getModuleLink('payco', 'confirmation');
            }else{
               $p_url_confirmation=Tools::getValue('P_URL_CONFIRMATION');
            }
            if(Tools::getValue('P_TITULO')==""){
               $p_titulo="Checkout ePayco, Tarjetas de Crédito, Débito y  Efectivo";
            }else{
              $p_titulo=Tools::getValue('P_TITULO');
            }

            Configuration::updateValue('P_CUST_ID_CLIENTE', Tools::getValue('P_CUST_ID_CLIENTE'));
            Configuration::updateValue('P_KEY', Tools::getValue('P_KEY'));
            Configuration::updateValue('PUBLIC_KEY', Tools::getValue('PUBLIC_KEY'));
            Configuration::updateValue('P_TEST_REQUEST', Tools::getValue('P_TEST_REQUEST'));
            Configuration::updateValue('P_TITULO', $p_titulo);
            Configuration::updateValue('P_URL_RESPONSE', $p_url_response);
            Configuration::updateValue('P_URL_CONFIRMATION', $p_url_confirmation);
            Configuration::updateValue('P_TYPE_CHECKOUT', Tools::getValue('P_TYPE_CHECKOUT'));
            Configuration::updateValue('P_STATE_END_TRANSACTION', Tools::getValue('P_STATE_END_TRANSACTION'));
            
            //CreditCard_OrderState::updateStates(intval(Tools::getValue('id_os_initial')), Tools::getValue('id_os_deleteon'));
            $this->_html.= '<div class="bootstrap"><div class="alert alert-success">'.$this->l('Cambios Aplicados Exitosamente') . '</div></div>'; 
       
      }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * This method is used to render the payment button,
     * Take care if the button should be displayed or not.
     */
    public function hookPayment($params)
    {
        if (!$this->active) 
            return false;

        $currency_id = $params['cart']->id_currency;
        $currency = new Currency((int)$currency_id);

        if (in_array($currency->iso_code, $this->limited_currencies) == false)
            return false;

        $this->smarty->assign('module_dir', $this->_path);

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');

    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $payment_options = [
            $this->getModalepayco(),
        ];

        return $payment_options;
    }
    public function getModalepayco()
    {
            
        $this->context->smarty->assign(array("titulo"=>$this->p_titulo));

        $modalOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $modalOption->setCallToActionText($this->l(''))
                      ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                      ->setAdditionalInformation($this->context->smarty->fetch('module:payco/views/templates/hook/payment_onpage.tpl'))
                      ->setLogo("https://369969691f476073508a-60bf0867add971908d4f26a64519c2aa.ssl.cf5.rackcdn.com/btns/cms/btn_prestashop.png");

        return $modalOption;
    }

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false)
            return;

        $order = $params['objOrder'];

        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')){
             $this->smarty->assign('status', 'ok');
        }
           

          $extra1 = $order->id_cart;
          $extra2 = $order->id;
          $emailComprador = $this->context->customer->email;
          $valorBaseDevolucion = $order->total_paid_tax_excl;
          $iva = $value - $valorBaseDevolucion;

          /*
          Para determinar la ubicación o por default CO
          */
          $iso = 'CO';
          //$valor = str_replace('.', '', $valor);
          if ($iva == 0) $valorBaseDevolucion = 0;

          $currency = $this->getCurrency();
          $idcurrency = $order->id_currency;
          foreach ($currency as $mon) {
            if ($idcurrency == $mon['id_currency']) $currency = $mon['iso_code'];
          }

          //si no existe la moneda
          if ($currency == ''){
            $currency = 'COP';
          }

          $refVenta = $order->reference;

          $state = $order->getCurrentState();

          if ($state) {

            $p_signature = md5(trim($this->p_cust_id_cliente).'^'.trim($this->p_key).'^'.$refVenta.'^'.$value.'^'.$currency);

            $addressdelivery = new Address(intval($cart->id_address_delivery));
            $addressbilling = new Address(intval($cart->id_address_invoice));
            
            if($this->p_test_request==1){
              $test="true";
            }else{
              $test="false";
            }


            $this->smarty->assign(array(
              'this_path_bw' => $this->_path,
              'p_signature' => $p_signature,
              'total_to_pay' => Tools::displayPrice($value, $currence, false),
              'status' => 'ok',
              'refVenta' => $refVenta,
              'custemail' => $emailComprador,
              'extra1' => $extra1,
              'extra2' => $extra2,
              'total' => $value,
              'currency' => $currency,
              'iso' => $iso,
              'iva' => $iva,
              'baseDevolucionIva' => $valorBaseDevolucion,
              'merchantid' => trim($this->p_cust_id_cliente),
              'external'=>$this->p_type_checkout,
              'merchantpassword' => trim($this->p_key),
              'merchanttest'=> $test,
              'p_key'=>trim($this->p_key),
              'public_key'=>trim($this->public_key),
              'custip' => $_SERVER['REMOTE_ADDR'],
              'custname' => ($cookie->logged ? $cookie->customer_firstname . ' ' . $cookie->customer_lastname : false),
              'p_url_response' => $this->p_url_response,
              'p_url_confirmation' => $this->p_url_confirmation,
              'p_billing_email' => $this->context->customer->email,
              'p_billing_name' => $this->context->customer->firstname,
              'p_billing_lastname' => $this->context->customer->lastname,
              'p_billing_address'=>$addressdelivery->address1 . " " . $addressdelivery->address2,
              'p_billing_city'=>$addressdelivery->city,
              'p_billing_country'=>$addressdelivery->id_state,
              'p_billing_phone'=>""
              )
            );

          } else {
              $smarty->assign('status', 'failed');
          }

        return $this->display(__FILE__, 'views/templates/hook/payment_return.tpl');
    }

    public function hookActionPaymentConfirmation()
    {
        
      
    }

    public function hookDisplayPaymentReturn($params)
    {
        /* Place your code here. */
        //var_dump("aaaa");
       // exit();


        if ($this->active == false)
            return;

        if (version_compare(_PS_VERSION_, '1.7.0.0 ', '<')){
            $order = $params['objOrder'];
            $value = $params['total_to_pay'];
            $currence = $params['currencyObj'];
        }else{
            $order = $params['order'];
            $value = $params['order']->getOrdersTotalPaid();
            $currence = new Currency($params['order']->id_currency);
        }

        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')){
             $this->smarty->assign('status', 'ok');
        }

          $cart=$this->context->cart;

          $extra1 = $order->id_cart;
          $extra2 = $order->id;
          $emailComprador = $this->context->customer->email;
          $valorBaseDevolucion = $order->total_paid_tax_excl;
          $iva = $value - $valorBaseDevolucion;

          /*
          Para determinar la ubicación o por default CO
          */
          $iso = 'CO';
          //$valor = str_replace('.', '', $valor);
          if ($iva == 0) $valorBaseDevolucion = 0;

          $currency = $this->getCurrency();
          $idcurrency = $order->id_currency;
          foreach ($currency as $mon) {
            if ($idcurrency == $mon['id_currency']) $currency = $mon['iso_code'];
          }

          //si no existe la moneda
          if ($currency == ''){
            $currency = 'COP';
          }

          $refVenta = $order->reference;

          $state = $order->getCurrentState();

          if ($state) {

            $p_signature = md5(trim($this->p_cust_id_cliente).'^'.trim($this->p_key).'^'.$refVenta.'^'.$value.'^'.$currency);

            $addressdelivery = new Address(intval($cart->id_address_delivery));
            $addressbilling = new Address(intval($cart->id_address_invoice));
            
            if($this->p_test_request==1){
              $test="true";
            }else{
              $test="false";
            }
            if($this->p_type_checkout==1){
                $external="false";
            }
            else{
                $external="true";
            }

            $this->smarty->assign(array(
              'this_path_bw' => $this->_path,
              'p_signature' => $p_signature,
              'total_to_pay' => Tools::displayPrice($value, $currence, false),
              'status' => 'ok',
              'refVenta' => $refVenta,
              'custemail' => $emailComprador,
              'extra1' => $extra1,
              'extra2' => $extra2,
              'total' => $value,
              'currency' => $currency,
              'iso' => $iso,
              'iva' => $iva,
              'baseDevolucionIva' => $valorBaseDevolucion,
              'merchantid' => trim($this->p_cust_id_cliente),
              'external'=>$external,
              'merchantpassword' => trim($this->p_key),
              'merchanttest'=> $test,
              'p_key'=>trim($this->p_key),
              'public_key'=>trim($this->public_key),
              'custip' => $_SERVER['REMOTE_ADDR'],
              'custname' => ($cookie->logged ? $cookie->customer_firstname . ' ' . $cookie->customer_lastname : false),
              'p_url_response' => $this->p_url_response,
              'p_url_confirmation' => $this->p_url_confirmation,
              'p_billing_email' => $this->context->customer->email,
              'p_billing_name' => $this->context->customer->firstname,
              'p_billing_last_name' => $this->context->customer->lastname,
              'p_billing_address'=>$addressdelivery->address1 . " " . $addressdelivery->address2,
              'p_billing_city'=>$addressdelivery->city,
              'p_billing_country'=>$addressdelivery->id_state,
              'p_billing_phone'=>$addressdelivery->phone_mobile
              )
            );

          } else {
              $smarty->assign('status', 'failed');
          }

        return $this->display(__FILE__, 'views/templates/hook/payment_return.tpl');

    }

    public function hookDisplayPaymentTop()
    {
        /* Place your code here. */
    }

    private function is_blank($var) {
        return isset($var) || $var == '0' ? ($var == "" ? true : false) : false;
    }

    private function checkCurrency($cart) {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module))
            foreach ($currencies_module as $currency_module)
                if ($currency_order->id == $currency_module['id_currency'])
                    return true;
        return false;
    }

    function PaymentReturnOnpage(){

      $ref_payco="";
      $url="";
     
      foreach ($_REQUEST as $key => $value) {
        if(preg_match("/ref_payco/", $value)){
          $arr_refpayco=explode("=",$value);
          $ref_payco=$arr_refpayco[1];
        }
      }
      if(isset($_REQUEST["x_ref_payco"])){
          $config = Configuration::getMultiple(array('P_CUST_ID_CLIENTE','P_KEY','PUBLIC_KEY','P_TEST_REQUEST'));  
          $public_key=$config["PUBLIC_KEY"];
          $ref_payco=$_REQUEST["x_ref_payco"];
          $url ="https://secure.payco.co/restpagos/transaction/state_transaction.json?ref_payco=$ref_payco&public_key=".$public_key;

      }  


      if(isset($_REQUEST["?ref_payco"])!="" || isset($_REQUEST["ref_payco"]) || $ref_payco!=""){

          if(isset($_REQUEST["?ref_payco"])){
            $ref_payco=$_REQUEST["?ref_payco"];
          }
          if(isset($_REQUEST["ref_payco"])){
             $ref_payco=$_REQUEST["ref_payco"];
          }

          $url = 'https://secure.epayco.co/validation/v1/reference/'.$ref_payco;
        }

        if($ref_payco!="" and $url!=""){

              $responseData = $this->PostCurl($url,false,$this->StreamContext());
        
              $jsonData = @json_decode($responseData, true);
              $data = $jsonData['data'];
              //Consultamos la transaccion en el servidor
              $data["ref_payco"]=$ref_payco;
             
              $this->Acentarpago($data["x_extra1"],$data["x_cod_response"],$data["x_ref_payco"],$data["x_transaction_id"],$data["x_amount"],$data["x_currency_code"],$data["x_signature"]);
             
              $this->context->smarty->assign($data);


        }

    }

    public function PaymentSuccess($extra1,$response,$referencia,$transid,$amount,$currency,$signature) {
      

      $this->Acentarpago($extra1,$response,$referencia,$transid,$amount,$currency,$signature,true);

    }


    private function Acentarpago($extra1,$response,$referencia,$transid,$amount,$currency,$signature,$confirmation) {

           $config = Configuration::getMultiple(array('P_CUST_ID_CLIENTE','P_KEY','PUBLIC_KEY','P_TEST_REQUEST'));  
           $x_cust_id_cliente=trim($config['P_CUST_ID_CLIENTE']);
           $x_key=trim($config['P_KEY']);
           $idorder=$extra1;
           $x_cod_response=(int)$response;
           $x_signature=hash('sha256',
            $x_cust_id_cliente.'^'
            .$x_key.'^'
            .$referencia.'^'
            .$transid.'^'
            .$amount.'^'
            .$currency
          );

          $payment=false;
          $state = 'PAYCO_OS_REJECTED';
          if ($x_cod_response == 4)
            $state = 'PAYCO_OS_FAILED';
          else if ($x_cod_response == 2)
            $state = 'PAYCO_OS_REJECTED';
          else if ($x_cod_response == 3)
            $state = 'PAYCO_OS_PENDING';
          else if ($x_cod_response == 1){
             $state = 'PS_OS_PAYMENT';
             $payment=true;
          }
        
          //Validamos la firma
          if($x_signature==$signature){

       
            $id_state=(int)Configuration::get($state);
        
            $order = new Order((int)Order::getOrderByCartId((int)$idorder));

            $current_state = $order->current_state;
            
            if ($current_state != Configuration::get($state))
            {
              
                $history = new OrderHistory();
                $history->id_order = (int)$order->id;
              
                if($payment){
                  $history->changeIdOrderState((int)$this->p_state_end_transaction, $order, true);
                }else{
                  $history->changeIdOrderState((int)Configuration::get($state), $order, true);
                }
                $history->addWithemail(false);
                 
            }
            if (!$payment)
            {
              foreach ($order->getProductsDetail() as $product)
                StockAvailable::updateQuantity($product['product_id'], $product['product_attribute_id'], + (int)$product['product_quantity'], $order->id_shop);
            }
            if($confirmation){
                header("HTTP/1.1 200 OK");
                echo "OK";
                exit();
            }

          }
                           
    }

    

    private function PostCurl($url){

        if (function_exists('curl_init')) {
                  $ch = curl_init();
                  $timeout = 5;
                  $user_agent='Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';
                  curl_setopt($ch, CURLOPT_URL, $url);
                  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                  curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
                  curl_setopt($ch, CURLOPT_HEADER, 0);
                  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                  curl_setopt($ch,CURLOPT_TIMEOUT,$timeout);
                  curl_setopt($ch,CURLOPT_MAXREDIRS,10);
                  $data = curl_exec($ch);
                  curl_close($ch);
                  return $data;
              }else{
                  $data =  @file_get_contents($url);
                  return $data;
              }
    }

    private function StreamContext(){

                $context = stream_context_create(array(
                    'http' => array(
                        'method' => 'POST',
                        'header' => 'Content-Type: application/x-www-form-urlencoded',
                        'protocol_version' => 1.1,
                        'timeout' => 10,
                        'ignore_errors' => true
                    )
                ));
    }

}
