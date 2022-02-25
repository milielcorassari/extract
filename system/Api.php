<?php

/** *
 * @author Miliel R de Lima
 * @since 12/11/2021
 */

class Api{

    private $url;
    private $method;
    private $dados = array();
    private $init = array();

    /**
     * Funcao construtora
     */
    public function __construct($api,$funcao,$auth,$method = "POST"){

        $this->method = $method;

        $this->url = "https://{$api}/api/{$funcao}";

        $this->init["header"] = array(
            "accept: */*",
            "Content-Type: application/json",
            "Authorization: {$auth}"
        );

        $this->init["options"][CURLOPT_URL] = $this->url;
        $this->init["options"][CURLOPT_HTTPHEADER] = $this->init["header"];
        $this->init["options"][CURLOPT_CUSTOMREQUEST] = $method;
        $this->init["options"][CURLOPT_SSL_VERIFYHOST ] = false;
        $this->init["options"][CURLOPT_SSL_VERIFYPEER ] = false;
        $this->init["options"][CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
        $this->init["options"][CURLOPT_RETURNTRANSFER] = true;
    }

    public function set($index,$dados){
        $this->dados[$index] = $dados;
    }

    public function get($index = null){
        if($index == null){
            return $this->dados;
        }else{
            return $this->dados["$index"];
        }
    }

    /**
     * Funcao para realizar a operação
     */
    public function conecta(){

        if($this->method == "POST" || $this->method == "PUT"){
            $this->init["options"][CURLOPT_POSTFIELDS] = json_encode($this->get());
        }

        $curl = curl_init();

        curl_setopt_array($curl, $this->init["options"]);

        $response = curl_exec($curl);
        $error = curl_error($curl);

        curl_close($curl);

        if($error){
            $r = $error;
        }else{
            $r = $response;
        }

        return json_decode($r,true);
    }
}
?>