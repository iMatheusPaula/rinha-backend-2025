<?php
require 'health.php';

while (true) {
    $default = getServiceHealth();
    var_dump($default);
    sleep(5);
}

// primeiro vamos mandar a requisicao para o gateway padrao,
// se retornar 200, vamos salvar no banco de dados o
// e incrementar a quantidade de pagamentos enviados para o default;
// se retornar qualquer status que comece diferente de 200,
// vamos verificar o $default[failing], se for true, mandamos para o gateway de fallback
// salva o amount e incrementa a quantidade de pagamentos enviados para o fallback;