<?php
require_once(__DIR__."/system/DB.php");
require_once(__DIR__."/system/Api.php");

$info = array();
$limit=$_REQUEST["limit"]??100;
$offset=$_REQUEST["offset"]??0;

/** definicao de parametros de-para */
$param_depara = array(
    "PLAN100/10" => array(
        "172.15.0.3" => "OLT1_Int_PPP_250_271",
        "172.15.0.4" => "OLT2_Int_PPP_251_272",
        "172.15.0.5" => "OLT3_Int_PPP_252_273"
    )
);
/** */

if(isset($_REQUEST["importar"])){

    $token = $_REQUEST["token"];
    $ip_host = $_REQUEST["ip_host"];
    $param = array(
        "host_db"=>$_REQUEST["host"],
        "schema_db"=>"aprosftthdata",
        "user_db"=>$_REQUEST["user"],
        "password_db"=>$_REQUEST["password"]
    );

    $project = new Api($ip_host,"ospmanager/projects",$token,"GET");
    $project_get = $project->conecta();
    $existe_project = false;

    if(isset($project_get["items"])){
        foreach($project_get["items"] as $x){
            $existe_project = $x["id"];
            break;
        }
    }

    if(!$existe_project){
        $info["WARNING"][] = "Cadastre primeiro um projeto para realizar a importação.";
        $info["WARNING"][] = $project_get;
    }else{
        $info["Project"] = $project_get["items"][0];

        if(isset($_REQUEST["dados"])){
    
            $data = new Operaction($param);

            // Cadastro de equipamentos
            if($_REQUEST["dados"]=="olt"){
    
                $get_olt = $data->find("select * from olt");

                foreach($get_olt as $v){

                    // prepara a requisicao
                    $set_olt = new Api($ip_host,"ispmanager/olt",$token,"POST");
                    $set_olt->set("description",$v["name"]);
                    $set_olt->set("ipAddress",$v["ip"]);
                    $set_olt->set("location","");
                    $set_olt->set("snmpV3Parameters",null);
                    $set_olt->set("snmpV2Parameters",array(
                        "community"=> $v["comunidad_write"] ?? "private"
                    ));
                    $set_olt->set("sshParameters",array(
                        "fingerprint"=> null,
                        "password"=> $v["password"],
                        "privilegedModePassword"=> $v["enable_password"],
                        "user"=> $v["username"]
                    ));

                    $response = $set_olt->conecta(); // executa requisicao

                    // verifica se nao cadastrar a olt
                    if(isset($response["fingerprint"])){

                        $set_olt->set("sshParameters",array(
                            "fingerprint"=> $response["fingerprint"],
                            "password"=> $v["password"],
                            "privilegedModePassword"=> $v["enable_password"],
                            "user"=> $v["username"]
                        ));

                        $response = $set_olt->conecta();
                    }

                    $response["DATA"] = array(
                        "name"=>$v["name"],
                        "ip"=>$v["ip"],
                        "username"=>$v["username"],
                        "password"=>$v["password"],
                        "enable_password"=>$v["enable_password"],
                        "comunidad"=>array(
                            "_read"=>$v["comunidad_read"],
                            "_write"=>$v["comunidad_write"]
                        ),
                        "latitude"=>$v["latitude"],
                        "longitude"=>$v["longitude"]
                    );

                    $info["OLTs_ONUs"][] = $response;
                }
            }

            // atribuicao de CTOs no mapa
            if($_REQUEST["dados"]=="cto"){

                $get_nap = $data->find(
                    "select nap.codigo as codigo_nap,nap.*,troncal_nap.* from plantel_exterior_ftth_nap as nap 
                    left join plantel_exterior_ftth_nap_salidas as nap_salida 
                    on nap_salida.id_plantel_exterior_ftth_nap = nap.id_plantel_exterior_ftth_nap 
                    left join plantel_exterior_ftth_troncales_nap as troncal_nap 
                    on troncal_nap.id_plantel_exterior_ftth_nap_salidas = nap_salida.id_plantel_exterior_ftth_nap_salidas 
                    group by nap.codigo order by nap.id_plantel_exterior_ftth_nap
                    LIMIT {$limit} OFFSET {$offset}"
                );

                foreach($get_nap as $x){

                    $splitters = array();

                    $get_splitters = $data->find(
                        "select splitters.* from aprosftthdata.plantel_exterior_ftth_splitter as splitters
                        left join aprosftthdata.plantel_exterior_ftth_armarios as armario On 
                            splitters.id_armario = armario.id_plantel_exterior_ftth_armarios
                        left join aprosftthdata.plantel_exterior_ftth_troncales_nap as troncales_nap On 
                            troncales_nap.id_plantel_exterior_ftth_armarios = armario.id_plantel_exterior_ftth_armarios
                        left join aprosftthdata.plantel_exterior_ftth_nap_salidas as nap_salidas on 
                            nap_salidas.id_plantel_exterior_ftth_nap_salidas = troncales_nap.id_plantel_exterior_ftth_nap_salidas
                        left join aprosftthdata.plantel_exterior_ftth_nap as nap on nap.id_plantel_exterior_ftth_nap = nap_salidas.id_plantel_exterior_ftth_nap
                        where 
                            nap.id_plantel_exterior_ftth_nap = {$x["id_plantel_exterior_ftth_nap"]}
                        group by splitters.id_plantel_exterior_ftth_splitter "
                    );

                    foreach($get_splitters as $key=>$s){

                        $ports = array();

                        for($i=0;$i<=intval($s["salidas"]);$i++){
                            if($i==0){
                                // porta input
                                $ports[] = array(
                                    "connector"=>false,
                                    "fusion"=>false,
                                    "id"=>null,
                                    "name"=>"input {$i}",
                                    "portConnected"=>null,
                                    "reservationCode"=>null,
                                    "status"=>"AVAILABLE",
                                    "type"=>"INPUT",
                                    "networkComponent"=>null
                                );
                            }else{
                                //porta output
                                $ports[] = array(
                                    "connector"=>false,
                                    "fusion"=>false,
                                    "id"=>null,
                                    "name"=>"output {$i}",
                                    "portConnected"=>null,
                                    "reservationCode"=>null,
                                    "status"=>"AVAILABLE",
                                    "type"=>"OUTPUT",
                                    "networkComponent"=>null
                                );
                            }
                        }

                        $splitters[] = array(
                            "id"=>null,
                            "address"=>"",
                            "balancing"=>$s["salidas"] == 2 ? "BALANCING_50x50" : null,
                            "connectorType"=>1,
                            "connectorized"=>true,
                            "name"=>"{$s["codigo"]}",
                            "latitude"=>0,
                            "longitude"=>0,
                            "level"=>"ACCESS",
                            "origin"=>"MANUAL",
                            "type"=>"SPLITTER_1X".$s["salidas"] ?? "2",
                            "visibleName"=>$s["codigo"],
                            "ports"=>$ports
                        );
                    }

                    // prepara a requisicao
                    $set_cto = new Api($ip_host,"ospmanager/projects/{$existe_project}/ctos",$token,"POST");
                    $set_cto->set("id",null);
                    $set_cto->set("address","");
                    $set_cto->set("connectorType",null);
                    $set_cto->set("createdDate",$_REQUEST["date_now"]);
                    $set_cto->set("lastModifiedDate",null);
                    $set_cto->set("installation","AIR_POLE");
                    $set_cto->set("latitude",floatval($x["latitude"]));
                    $set_cto->set("longitude",floatval($x["longitude"]));
                    $set_cto->set("name",$x["codigo_nap"]);
                    $set_cto->set("note",$x["comentario"]);
                    $set_cto->set("operationalStatus","PENDING");
                    $set_cto->set("origin","MANUAL");
                    $set_cto->set("referencePoint","");
                    //$set_cto->set("sensed",false);
                    $set_cto->set("status","ACTIVE");
                    $set_cto->set("type",193);
                    $set_cto->set("sensors",[]);
                    $set_cto->set("splitters",$splitters);
                 
                    $response = $set_cto->conecta();

                    $response["DATA"] = $set_cto->get();
                    $info["CTOs"][] = $response;
                }
            }

            // atribuicao de ONUs no mapa
            if($_REQUEST["dados"]=="onu"){

                $get_cto = new Api($ip_host,"ospmanager/projects/{$existe_project}/ctos",$token,"GET");
                $response_cto = $get_cto->conecta();
                $response_cto = array_slice($response_cto,$offset,$limit); // controla o limit offset do array da CTO

                //get nap.codigo
                foreach($response_cto as $cto){
                    
                    $get_onus = $data->find(
                        "SELECT * 
                        (select direccion from aprosftthdata.usuarios where id_usuarios = r_onu.id_usuarios) as address,
                        o_type.name as name_type,
                        r_onu.mac,
                        r_onu.latitude,
                        r_onu.longitude,
                        r_onu.id_cross_port
                        ,nap.codigo as codigo_nap                        
                        from aprosftthdata.reservation_onu as r_onu                        
                        left join aprosftthdata.onu_type as o_type on o_type.id_onu_type = r_onu.id_onu_type
                        left join aprosftthdata.plantel_exterior_ftth_troncales_nap as troncal_nap
                            on troncal_nap.id_plantel_exterior_ftth_troncales_nap = 
                            (select id_plantel_exterior_ftth_troncales_nap from aprosftthdata.cross_port where id_cross_port = r_onu.id_cross_port)
                        left join aprosftthdata.plantel_exterior_ftth_nap as nap 
                            on nap.id_plantel_exterior_ftth_nap = 
                            (select id_plantel_exterior_ftth_nap from aprosftthdata.plantel_exterior_ftth_nap_salidas where id_plantel_exterior_ftth_nap_salidas = troncal_nap.id_plantel_exterior_ftth_nap_salidas)
                        where nap.codigo = '{$cto["name"]}'
                        "
                    );

                    if(is_array($get_onus)){
                        foreach($get_onus as $onu){
                        
                            $onu_type = 0;
                            $onu_connectorType = null;
                            $get_onu_type = new Api($ip_host,"ospmanager/onu-types",$token,"GET");
                            $response_onu_type = $get_onu_type->conecta();
    
                            foreach($response_onu_type as $t){
                                if($onu["name_type"] == $t["model"]){

                                    $onu_type = $t["id"];
                                    $onu_connectorType = $t["connectorType"];

                                    $set_onu = new Api($ip_host,"ospmanager/projects/{$existe_project}/onus",$token,"POST");
                                    $set_onu->set("id",null);
                                    $set_onu->set("name","{$onu["mac"]}");
                                    $set_onu->set("latitude",floatval($onu["latitude"]));
                                    $set_onu->set("longitude",floatval($onu["longitude"]));
                                    $set_onu->set("address",$onu["address"]);
                                    $set_onu->set("connectorType",null);
                                    $set_onu->set("createdDate",$_REQUEST["date_now"]);
                                    $set_onu->set("lastModifiedDate",null);
                                    $set_onu->set("type",$onu_type); // Id tipo ONU
                                    $set_onu->set("gponSerialNumber",""); // N serial ONU
                                    $set_onu->set("macAddress",$onu["mac"]);
                                    $set_onu->set("managementState","UNMANAGED");
                                    $set_onu->set("administrativeState","ACTIVE");
                                    $set_onu->set("operationalState","ACTIVE");
                                    $set_onu->set("description",""); // Nome de modelo
                                    $set_onu->set("note","");
                                    $set_onu->set("referencePoint","");
                                    $set_onu->set("contractId",""); // se selectedOnu.administrativeState != 'PLANNING'
                                    $set_onu->set("georeferenced",true);
            
                                    $response = $set_onu->conecta();
            
                                    $response["DATA"] = $set_onu->get();
                                    $info["ONUs"][] = $response;

                                    break;
                                }
                            }
                        }
                    }                    
                }             
            }

            // conectar fibra ONU x CTO
            if($_REQUEST["dados"]=="fibra"){

                $get_onus = new Api($ip_host,"ospmanager/projects/{$existe_project}/onus",$token,"GET");
                $response_onus = $get_onus->conecta();
                $response_onus = array_slice($response_onus,$offset,$limit); // controla o limit offset do array da ONUs

                foreach($response_onus as $onu){

                    $points = array();
                    $ports = array();
                    $port_output = $onu["ports"][0]["id"];
                    $port_input = 0;
                    
                    $query = "SELECT
                    s.codigo as splliter,
                    n.codigo as nap,
                    n_saida.salida as saida_nap
                    FROM aprosftthdata.reservation_onu as r
                    inner join aprosftthdata.cross_port as p on p.id_cross_port = r.id_cross_port
                    inner join aprosftthdata.plantel_exterior_ftth_splitter_salidas as s_saida on s_saida.id_plantel_exterior_ftth_splitter_salidas = p.id_splitter_salidas
                    inner join aprosftthdata.plantel_exterior_ftth_splitter as s on s.id_plantel_exterior_ftth_splitter = s_saida.id_plantel_exterior_ftth_splitter
                    inner join aprosftthdata.plantel_exterior_ftth_troncales_nap as t_nap on t_nap.id_plantel_exterior_ftth_troncales_nap = p.id_plantel_exterior_ftth_troncales_nap
                    inner join aprosftthdata.plantel_exterior_ftth_nap_salidas as n_saida on n_saida.id_plantel_exterior_ftth_nap_salidas = t_nap.id_plantel_exterior_ftth_nap_salidas
                    inner join aprosftthdata.plantel_exterior_ftth_nap as n on n.id_plantel_exterior_ftth_nap = n_saida.id_plantel_exterior_ftth_nap ";

                    // verifica se vai importar por mac addres especificos
                    if(isset($_REQUEST["array_onu"]) && !empty($_REQUEST["array_onu"])){
                        $name_onus = explode(",",$_REQUEST["array_onu"]);
                        $query .= "where r.mac in('".implode("','",$name_onus)."') ";
                    }else{
                        $query .= "where = '{$onu["name"]}' ";
                    }

                    $get_port_input = $data->find($query);
    
                    foreach($get_port_input as $in){

                        $get_cto = new Api($ip_host,"ospmanager/projects/{$existe_project}/ctos?CTOName={$in["nap"]}",$token,"GET");
                        $cto = $get_cto->conecta()[0];
    
                        foreach($cto["splitters"] as $sp){
                            
                            if($sp["name"] == $in["splliter"]){    
                                foreach($sp["ports"] as $pt){

                                    if(strpos($pt["name"], "output port ".(intval($in["saida_nap"]) - 1))){
                                        //get port
                                        $ports[] = array(
                                            "connector"=>false,
                                            "fusion"=>false,
                                            "id"=>null,
                                            "name"=>$pt["name"],
                                            "portConnected"=>null,
                                            "reservationCode"=>null,
                                            "status"=>"AVAILABLE",
                                            "type"=>"INPUT",
                                            "networkComponent"=>null
                                        );
                                        $points = array(
                                            "latitude"=>floatval($cto["latitude"]),
                                            "longitude"=>floatval($cto["longitude"])
                                        );
                                        $port_input = $pt["id"];
                                        break;
                                    }
                                }
                            }
                        }   
                    }

                    $ports[] = array(
                        "connector"=>false,
                        "fusion"=>false,
                        "id"=>null,
                        "name"=>$onu["ports"][0]["name"],
                        "portConnected"=>null,
                        "reservationCode"=>null,
                        "status"=>"AVAILABLE",
                        "type"=>"OUTPUT",
                        "networkComponent"=>null
                    );
    
                    $set_fiber = new Api($ip_host,"ospmanager/projects/{$existe_project}/fibers",$token,"POST");
                    $set_fiber->set("id",null);
                    $set_fiber->set("input",$port_input); // id da entrada
                    $set_fiber->set("latitude",0);
                    $set_fiber->set("length",0);
                    $set_fiber->set("longitude",0);
                    $set_fiber->set("name","fiber");
                    $set_fiber->set("order",null);
                    $set_fiber->set("output",$port_output); // id da saida
                    $set_fiber->set("ports",$ports); // porta input e output
                    $set_fiber->set("segment",null);
                    $set_fiber->set("status","OK");
                    $set_fiber->set("type",1); // 
                    $set_fiber->set("wayPoints",array(
                        array(
                            "latitude"=>floatval($onu["latitude"]),
                            "longitude"=>floatval($onu["longitude"])
                        ),
                        $points
                    )); // pontos de longitude e latitude da CTO e ONU

                    if(!empty($points)){
                        $response = $set_fiber->conecta();
                    }

                    $response["DATA"] = $set_fiber->get();
                    $response["DATA"]["ONU"] = $onu["name"];
                    $info["FIBERs"][] = $response;
                }                
            }

            // Dados VLANs e Servicos
            if($_REQUEST["dados"]=="servico"){

                /**
                 * Get das VLAN no CMAP
                 */
                $get_vlan = $data->find(
                    "SELECT aprosftthdata.vlan.*,olt.id_olt as co FROM aprosftthdata.vlan as vlan
                    left join aprosftthdata.olt_vlan as olt on olt.id_lan2lan = vlan.id_lan2lan
                    group by vlan.id_lan2lan"
                );

                foreach($get_vlan as $v){

                    /**
                     * API: ispmanager/vlan
                     * Cadastro de VLANs
                     * Payloads: {"remarks"=>"Observações","name"=>"INTERNET","vid"=>"010","cos"=>"1","serviceType"=>"INTERNET"}
                     */
                    $set_vlan = new Api($ip_host,"ispmanager/vlan",$token,"POST");
                    $set_vlan->set("name",preg_replace('/[0-9\@\.\;\-\" "]+/', '', $v["nombre"]));
                    $set_vlan->set("vid",$v["vlan"]);
                    $set_vlan->set("cos",$v["co"]);

                    if(strtoupper($v["nombre"]) == "GESTION"){
                        $set_vlan->set("serviceType","MANAGEMENT");
                    }else
                    if(strtoupper($v["nombre"]) == "VOIP"){
                        $set_vlan->set("serviceType","TELEPHONY");
                    }else
                    if(strpos(strtoupper($v["nombre"]),"INTERNET") !== false){
                        $set_vlan->set("serviceType","INTERNET");
                    }else{
                        $set_vlan->set("serviceType","DATA");
                    }

                    $set_vlan->set("remarks","");
                    $response = $set_vlan->conecta();

                    $response["DATA"] = $set_vlan->get();
                    $info["VLAN"][] = $response;
                }

                /**
                 * get sip no CMAP
                 */
                /*$get_sip = $data->find(
                    "SELECT * FROM aprosftthdata.soft_switch "
                );*/

                /**
                 * Get profile no CMAP
                 */
                $get_profile = $data->find(
                    "SELECT 

                        pf.name,
                        bridge.vlan,bridge.bw_down,bridge.bw_up,bridge.ports,
                        r.wifi_ssid,r.wifi_password,r.wlan_channel,r.wlan_mode, r.type_network,r.wlan_frequency,
                        lan.local_ip,lan.subnetmask,dhcp_iprange_end,lan.dhcp_iprange_start,
                        -- dmz.dmz,dmz.ipaddr,dmz.fixed_flag,
                        -- enc.external_port_from,enc.external_port_to,enc.protocol,enc.rule_name,enc.target_ip,enc.target_netmask,enc.internal_port_from,enc.internal_port_to,
                        pppoe.enable as pppoe_enable,pppoe.ip,pppoe.mask,pppoe.username,pppoe.password,pppoe.sesiones
                    
                    FROM aprosftthdata.reservation_onu as r
                    
                    left join aprosftthdata.lan_configuration_onu as lan on lan.id_lan_configuration_onu = r.id_lan_configuration_onu
                    -- left join aprosftthdata.dmz_configuration as dmz on dmz.id_reservation_onu = r.id_reservation_onu
                    -- left join aprosftthdata.forwarding_configuration as enc on enc.id_reservation_onu = r.id_reservation_onu
                    left join aprosftthdata.onu_profile as pf on pf.id_onu_profile = r.id_onu_profile
                    left join aprosftthdata.reservation_pppoe as pppoe on pppoe.id_usuario = r.id_usuarios
                    left join aprosftthdata.bridge as bridge on bridge.id_onu_profile = r.id_onu_profile and host = 'ppp'
                    
                    where bridge.vlan is not null
                    
                    group by pf.name"
                );

                foreach($get_profile as $v){

                    /**
                     * get VLAN
                     * API: ispmanager/vlan?vid=270
                     * Payload: 
                     */
                    $get_vlan = new Api($ip_host,"ispmanager/vlan?vid={$v["vlan"]}",$token,"GET");
                    $vlan = $get_vlan->conecta();

                    /**
                     * API: ispmanager/internet-service
                     * Cadastro de configurações de serviço de internet
                     */

                    $set_internet = new Api($ip_host,"ispmanager/internet-service",$token,"POST");
                    $set_internet->set("name",$v["name"]);
                    $set_internet->set("vlanId",$vlan["content"][0]["id"]);
                    $set_internet->set("maxUpstream",$v["bw_up"]);
                    $set_internet->set("maxDownstream",$v["bw_down"]);
                    $set_internet->set("ports",array(1,2,3,4));
                    $set_internet->set("bridgeService", array("enabled"=>($v["pppoe_enable"] == 1) ? false : true));

                    if($v["pppoe_enable"] == 1){
                        // tipo: Router

                        $set_internet->set(
                            "routerService",
                            array(
                                "lan"=>array(
                                    "lanIpAddress"=>$v["local_ip"] ?? "192.168.1.1",
                                    "networkPrefixSize"=>24,
                                    "dhcpEnabled"=>true,
                                    "dhcpServerStart"=>$v["dhcp_iprange_start"] ?? "192.168.1.2",
                                    "dhcpServerEnd"=>$v["dhcp_iprange_end"] ?? "192.168.1.254",
                                    "leasedTime"=>86400,
                                    "dnsProxyEnabled"=>true
                                ),
                                "wan"=>array(
                                    "wanType"=>'PPPOE', //($v["pppoe"] == 1) ? 'PPPOE' : 'DYNAMIC_IPOE',
                                    "addressProtocolCombination"=>"IPv4_AND_IPv6",
                                    "dnsProxyAddressProtocol"=>'DISABLED', //($v["pppoe"] == 1) ? 'DISABLED' : 'IPV4',
                                    "dnsList"=>array()
                                ),
                                "wifi"=>array(
                                    "wifi2G"=>array(
                                        "wifiIndex"=>1,
                                        "wifiRegion"=>"UNITED_STATES",
                                        "wifiMode"=>"B_G_N",
                                        "wifiChannel"=>"AUTO",
                                        "wifiChannelWidth"=>"_20",
                                        "wifiSecurityType"=>"WPA_WPA2_PSK",
                                        "wpaVersion"=>"AUTO",
                                        "wpaEncryption"=>"AES"
                                    ),
                                    "wifi5G"=>array(
                                        "wifiIndex"=>2,
                                        "wifiRegion"=>"ARGENTINA",
                                        "wifiMode"=>"AC",
                                        "wifiChannel"=>"AUTO",
                                        "wifiChannelWidth"=>"_80",
                                        "wifiSecurityType"=>"WPA_WPA2_PSK",
                                        "wpaVersion"=>"AUTO",
                                        "wpaEncryption"=>"AES_TKIP"
                                    )
                                )
                            )
                        );
                    }else{
                        // tipo: Bridge
                        $set_internet->set(
                            "routerService",array()
                        );
                    }

                    $response = $set_internet->conecta();
                    $id_internet = $response;

                    $response["DATA"] = $set_internet->get();
                    $info["SERVICO_INTERNET"][] = $response;
                    /** */                   
                }
            }

            // Dados Pacotes e Ativacoes
            if($_REQUEST["dados"]=="pacote"){

                /**
                 * get VLAN de Gerenciamento
                 * API: ispmanager/vlan?serviceType=MANAGEMENT
                 * Payload: 
                 */
                $get_vlan = new Api($ip_host,"ispmanager/vlan?serviceType=MANAGEMENT",$token,"GET");
                $vlan = $get_vlan->conecta();

                /**
                 * get Servico de Internet
                 * API: ispmanager/internet-service
                 * Payload: 
                 */
                $get_servico = new Api($ip_host,"ispmanager/internet-service",$token,"GET");
                $servicos = $get_servico->conecta();

                foreach($servicos["content"] as $v){

                    /**
                     * API: ispmanager/servicepackage
                     * Cadastro de pacotes de servico
                     * {"servicesType":["ROUTER","WIFI"],"category":"RESIDENTIAL","name":"PACOTE_2M","internetServiceId":1,"managementVlanId":4,"enabled":true}
                     */

                    $set_pacote = new Api($ip_host,"ispmanager/servicepackage",$token,"POST");
                    $set_pacote->set("servicesType",array(
                            "ROUTER","WIFI"
                        )
                    );
                    $set_pacote->set("category","RESIDENTIAL");
                    $set_pacote->set("name",$v["name"]);
                    $set_pacote->set("internetServiceId",$v["id"]);
                    $set_pacote->set("managementVlanId",$vlan["content"][0]["id"]);
                    $set_pacote->set("enabled","true");
                    $response = $set_pacote->conecta();

                    $response["DATA"] = $set_pacote->get();
                    $info["SERVICO_PACOTE"][] = $response;
                    /** */                    
                }                
            }
            
            if($_REQUEST["dados"] == "ativacao"){

                /**
                 * get Usuarios no CMAP
                 */
                $get_usuario = $data->find(
                    "SELECT 

                    u.nombre,u.apellido,u.n_cliente,u.email,u.direccion,(select nombre from aprosftthdata.ciudades where id_ciudades = u.id_ciudad) as cidade,
                    onu.name as pacote,
                    olt.ip,
                    r.wifi_ssid,r.wifi_password,r.wlan_channel,r.wlan_mode, r.type_network,r.wlan_frequency,r.tel_number1,r.tel_number2,r.tel_pwd1,r.tel_pwd2,
                    pppoe.username as pppoe_user,pppoe.password as pppoe_pass
                    
                    FROM aprosftthdata.usuarios as u
                    
                    left join aprosftthdata.reservation_onu AS r on r.id_usuarios = u.id_usuarios
                    left join aprosftthdata.onu_profile as onu on onu.id_onu_profile = r.id_onu_profile
                    left join aprosftthdata.olt as olt on olt.id_olt in(select distinct id_olt from aprosftthdata.onu_profile_olt where id_onu_profile = onu.id_onu_profile)
                    left join aprosftthdata.reservation_pppoe as pppoe on pppoe.id_usuario = r.id_usuarios
                    LIMIT {$limit} OFFSET {$offset}
                    "
                );

                foreach($get_usuario as $u){

                    $pacote = $u["pacote"];

                    /**
                     * Get ID Olt
                     */
                    $get_olt = new Api($ip_host,"ispmanager/olt?ipAddress={$u["ip"]}",$token,"GET");
                    $olt = $get_olt->conecta();

                    /**
                     * Comparar pacotes DE-PARA
                     */
                    foreach($param_depara as $key=>$value){
                        if($key==$pacote){                            
                            foreach($value as $ind=>$pack){
                                if($ind==$u["ip"]){
                                    $pacote = $pack;
                                    break;
                                }
                            }
                        }
                    }
                     /** */

                    /**
                     * Get pacote
                     */
                    $get_pacote = new Api($ip_host,"ispmanager/servicepackage?name={$pacote}",$token,"GET");
                    $pacote = $get_pacote->conecta();

                    /**
                     * Get Service
                     */
                    $get_service = new Api($ip_host,"ispmanager/internet-service/{$pacote["content"][0]["internetServiceId"]}",$token,"GET");
                    $servico = $get_service->conecta();

                    /**
                     * API: ispmanager/activity
                     * Cadastro de ativacao
                     * 
                     */
                    $set_ativacao = new Api($ip_host,"ispmanager/activity",$token,"POST");
                    $set_ativacao->set("contract",$u["n_cliente"]);
                    $set_ativacao->set("oltId",$olt["content"][0]["id"]); // mudar aqui para realizar testes sem estar na vpn do cliente                    
                    $set_ativacao->set("onuDescription","{$u["nombre"]} {$u["apellido"]}"); // truncar para 30 caracter max
                    $set_ativacao->set("osNumber",$u["n_cliente"]);
                    $set_ativacao->set(
                        "router",
                        array(
                            "authentication"=>"AUTO",
                            "password"=>$u["pppoe_pass"],
                            "username"=>$u["pppoe_user"]
                        )
                    );
                    $set_ativacao->set("servicePackageId",$pacote["content"][0]["id"]);
                    //$set_ativacao->set("telephonies",[]);
                    $set_ativacao->set(
                        "telephonies",
                        array(
                            "password"=>$u["tel_pwd1"] ?? $u["tel_pwd2"],
                            "telNumber"=>$u["tel_number1"] ?? $u["tel_number2"],
                            "username"=>$u["tel_number1"] ?? $u["tel_number2"]
                        )
                    );
                    $set_ativacao->set(
                        "wifi",
                        array(
                            "ssid2G"=>$u["wifi_ssid"],
                            "password2G"=>$u["wifi_password"],
                            "ssid5G"=>"{$u["wifi_ssid"]}_5G",
                            "password5G"=>$u["wifi_password"],
                            "wifiParameters2G"=>$servico["routerService"]["wifi"]["wifi2G"],
                            "wifiParameters5G"=>$servico["routerService"]["wifi"]["wifi5G"]
                        )
                    );
                    $response = $set_ativacao->conecta();

                    $response["DATA"] = $set_ativacao->get();
                    $info["CENTRAL_ATIVACAO"][] = $response;
                }
            }
        }
    }    
}

if(isset($_REQUEST["extrair"])){
    
    /** Extrai dados de cliente do CMAP */
    /**
     * get Usuarios no CMAP
     */
    $get_usuario = $data->find(
        "SELECT 

        u.nombre,u.apellido,u.n_cliente,u.email,u.direccion,(select nombre from aprosftthdata.ciudades where id_ciudades = u.id_ciudad) as cidade,
        r.tel_number1,r.tel_number2,r.tel_pwd1,r.tel_pwd2
        
        FROM aprosftthdata.usuarios as u
        
        left join aprosftthdata.reservation_onu AS r on r.id_usuarios = u.id_usuarios
        "
    );

    // gerar csv de dados exspecificos da pessoa
    $arq_person = fopen("./csv/extract_person_".date("Y-m-d-H-i-s").".csv", "a");
    fputcsv($arq_person , ["nome","sobrenome","email","numero_telefone","cidade","pais","status"]);

    foreach($get_usuario as $u){
        fputcsv($arq_person,[$u["nombre"],$u["apellido"],$u["email"],"{$u["tel_number1"]} - {$u["tel_number2"]}",$u["cidade"],$u["direccion"],""]);
    }

    $info["EXTRACT_CLIENT"][] = $get_usuario;

    fclose($arq_person);
    /***************************************** */

    /** Extrai dados de Usuario no CMAP */
    /**
     * get Usuarios no CMAP
     */
    $get_usuario = $data->find(
        "SELECT 

        (SELECT name from aprosftthdata.onu_type where id_onu_profile_type = onu.id_onu_profile_type limit 1) as model_onu,
        r.mac,
        olt.ip as olt_ip, olt.name as olt_name,olt.id_olt,-- slot,port
        u.nombre,u.apellido,u.direccion,
        -- saida_nap, armario
        u.n_cliente,onu.name as perfil,
        pppoe.username as pppoe_user,pppoe.password as pppoe_pass,
        r.wifi_ssid,r.wifi_password,
        dmz.ipaddr as dmz_ip,
        fowarding.external_port_from,fowarding.external_port_to,fowarding.internal_port_from,fowarding.internal_port_to,
        r.tel_number1,r.tel_number2,r.tel_pwd1,r.tel_pwd2
        
        FROM aprosftthdata.usuarios as u
        
        left join aprosftthdata.reservation_onu AS r on r.id_usuarios = u.id_usuarios
        left join aprosftthdata.onu_profile as onu on onu.id_onu_profile = r.id_onu_profile
        left join aprosftthdata.olt as olt on olt.id_olt in(select distinct id_olt from aprosftthdata.onu_profile_olt where id_onu_profile = onu.id_onu_profile)
        left join aprosftthdata.reservation_pppoe as pppoe on pppoe.id_usuario = r.id_usuarios
        left join aprosftthdata.dmz_configuration as dmz on dmz.id_reservation_onu = r.id_reservation_onu
        left join aprosftthdata.forwarding_configuration as fowarding on fowarding.id_reservation_onu = r.id_reservation_onu
        LIMIT {$limit} OFFSET {$offset}
        "
    );

    // gerar csv de dados exspecificos da pessoa
    $arq_person = fopen("./csv/extract_person_".date("Y-m-d-H-i-s").".csv", "a");
    fputcsv($arq_person , ["Modelo de ONU","MAC","OLT","Slot","Port","Id","Nome","Sobrenome","Endereço","Saída NAP","Armário","Nr. Cliente","Perfil","PPPoE_User","PPPoE_Psswd","WIFI_SSID","WIFI_PSSWD","DMZ_IP","Port-External-Fowarding","Port-Internal-Fowarding","Tel_user1","Tel_Passwd1","Tel_user2","Tel_Passwd2"]);		

    foreach($get_usuario as $u){
        fputcsv($arq_person,[$u["model_onu"],$u["mac"],$u["olt_name"],"","",$u["olt_ip"],$u["nombre"],$u["apellido"],$u["direccion"],"","",$u["n_cliente"],$u["perfil"],"PPPoE_User",$u["pppoe_user"],$u["pppoe_pass"],$u["wifi_ssid"],$u["wifi_password"],$u["dmz_ip"],$u["external_port_from"]." - ".$u["external_port_to"],$u["internal_port_from"]." - ".$u["internal_port_to"],$u["tel_number1"],$u["tel_number2"],$u["tel_pwd1"],$u["tel_pwd2"]]);
    }

    $info["EXTRACT_CLIENT"][] = $get_usuario;

    fclose($arq_person);
    /***************************** */
}

// gerar log na pasta log.
$fp = fopen("./log/log-".date("Y-m-d-H-i-s").".json", "a");
$escreve = fwrite($fp, json_encode($info));
fclose($fp);

?>

<form action="" method="post">
    <input type="hidden" name="date_now" id="date_now" readonly>
    <table>
        <tr>
            <td colspan="3">
                <h3>Informe os dados de conexão com o Conscius Manager</h3>
                <p>Local onde será importado os dados</p>
            </td>
        </tr>
        <tr>
            <th>API/IP Host</th>
            <th colspan="2">Token (HEADER: Authorization)</th>
        </tr>
        <tr>
            <td><input type="text" name="ip_host" required value="172.27.78.212"></td>
            <td colspan="2"><input type="text" name="token" required value="<?=$_REQUEST["token"];?>"></td>
        </tr>
        <tr><td colspan="3"><hr></td></tr>
        <tr>
            <td colspan="3">
                <h3>Informe os dados de conexão com a Base de Dados do Conscius Map</h3>
                <p>Base de dados de onde será extraido os dados</p>
            </td>
        </tr>
        <tr>
            <th>Host</th>
            <th>User</th>
            <th>Password</th>
        </tr>
        <tr>
            <td><input type="text" name="host" required value="172.27.78.225"></td>
            <td><input type="text" name="user" required value="root"></td>
            <td><input type="text" name="password" required value="From$hell#2003"></td>
        </tr>
        <tr>
            <td colspan="3">
                <p>Informe quantidade de registros que serão extraídos</p>
            </td>
        </tr>
        <tr>
            <th>LIMIT</th>
            <th>OFFSET</th>
        </tr>
        <tr>
            <td><input type="text" name="limit" required value="100"></td>
            <td><input type="text" name="offset" required value="0"></td>
        </tr>
        <tr><td colspan="3"><hr></td></tr>
        <tr>
            <td colspan="3">
                <h3>Selecione quais dados serão extraidos do CMap</h3>
            </td>
        </tr>
        <tr>
            <td colspan="4">
                <p>Cadastro de Equipamento</p>

                <input type="radio" name="dados" id="dados_olt" value="olt">
                <label>OLTs e ONUs</label>

                <p>Atribuição de dados no MAPA:</p>

                <input type="radio" name="dados" id="dados_cto" value="cto">
                <label>CTOs/NAP</label>

                <input type="radio" name="dados" id="dados_onu" value="onu">
                <label>ONUs/ONTs</label>

                <input type="radio" name="dados" id="dados_onu" value="fibra">
                <label>Conectar Fibra CTO - ONU</label>

                <p>Informar MAC das ONUs que deseja conectar a fibra separados por virgula</p>
                <input type="text" name="array_onu" id="array_onu" value="">
                <hr>

                <p>Configurações de VLANs, serviços, cadastros de pacotes e ativação</p>

                <input type="radio" name="dados" id="dados_servico" value="servico">
                <label>VLANs e Cadastros de Serviços</label>

                <input type="radio" name="dados" id="dados_pacote" value="pacote">
                <label>Pacotes de Servicos</label>

                <input type="radio" name="dados" id="dados_ativacao" value="ativacao">
                <label>Central de Ativacao</label>
            </td>
        </tr>
        <tr><td colspan="3"><hr></td></tr>       
        <tr>
            <td><button type="submit" name="importar">IMPORTAR DADOS</button></td>
            <td><button type="submit" name="extrair">EXTRAIR DADOS</button></td>
        </tr>
    </table>
</form>

<script>
    document.getElementById("date_now").value = new Date().toISOString();
</script>

<hr>

<h4>Arquivo Log gerado...</h4>

<pre><?=print_r($info) ?? "";?></pre>
