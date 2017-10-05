{*
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
*}
{if $status == 'ok'}
   
<div style="text-align: center;">
  Enviando a transacción de pago... si el pedido no se envia automaticamente de click en el botón "Pagar con ePayco"

  <a id="btn-pagar" href="#" onclick="open_checkout();"><img src="https://369969691f476073508a-60bf0867add971908d4f26a64519c2aa.ssl.cf5.rackcdn.com/btns/epayco/boton_de_cobro_epayco2.png" /></a>
</div>

<script type="text/javascript" src="https://s3-us-west-2.amazonaws.com/epayco/v2.0/v2Checkout.js" >   </script>
<script type="text/javascript" >


    var handler = ePayco.checkout.configure({
        key: "{$public_key}",
        test: {$merchanttest}
    })
    var data = { 
            amount: "{$total}",
            base_tax:"{$base_tax}",
            tax:"{$tax}",
            name: "ORDEN DE COMPRA # {$refVenta}",
            description: "ORDEN DE COMPRA # {$refVenta}",
            currency: "{$currency|lower}",
            country: "{$iso|lower}",
            lang: "es",
            external:"{$external}",
            extra1:"{$extra1}",
            extra2:"{$extra2}",
            extra3:"",
            invoice: "{$refVenta}",
            confirmation: "{$p_url_confirmation}",
            response: "{$p_url_response}",
            email_billing: "{$p_billing_email}",
            name_billing: "{$p_billing_name} {$p_billing_last_name}",
            address_billing: "{$p_billing_address}",
            phone_billing:"{$p_billing_phone}"
        }
        setTimeout(function(){ 
            handler.open(data);
         }, 2000);


        function open_checkout(){
            handler.open(data);
        }


     </script>

{else}
<p class="warning">
  {l s='Hemos notado un problema con tu orden, si crees que es un error puedes contactar a nuestro' mod='ev1enlinea'}
  <a href="{$base_dir_ssl}contact-form.php">{l s='Departamento De Soporte' mod='ev1enlinea'}</a>.
</p>
{/if}