<?php
$myhost = $_GET['host']; 

?><script src="//api-maps.yandex.ru/2.0/?load=package.standard&lang=ru-RU" type="text/javascript"></script>
<script type="text/javascript">
  ymaps.ready(geoinit);
  function geoinit() {
    var mycity = ymaps.geolocation.city;
    setTimeout('top.postMessage({"str":"mymap","city":"'+mycity+'"}, "http://<?=$myhost?>")', 50);
  }
</script>
