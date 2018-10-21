
<!-- zibal.ir Online Payment Module -->
<p class="payment_module">
  <a href="javascript:$('#zibal').submit();" class="zibal" title="online Payment with zibal.ir">
    <img src="modules/zibal/logo.png" alt="online Payment with zibal.ir" style="margin-left:20px;" />
 پرداخت آنلاین با درگاه پرداخت زیبال
  </a>
</p>
<form id="zibal" action="modules/zibal/process.php?do=payment" method="post" class="hidden">
  <input type="hidden" name="orderId" value="{$orderId}" />
</form>
<!-- End of zibal.ir Online Payment Module-->

