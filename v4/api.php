<?php

#ini_set('display_startup_errors', 1);
#ini_set('display_errors', 1);
#error_reporting(-1);
#This is EPAPI 4
# New features: Option to enable or disable anti-sql-injection attacks, telemetry into "history" table

$LOG_HISTORY = true;

function exception_error_handler(int $errno, string $errstr, string $errfile = null, int $errline) {
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler(exception_error_handler(...));
function error($errornumber,$errormessage) {
    http_response_code($errornumber);
    $final = array();
    $final['message'] = $errormessage;
    echo json_encode($final);
    exit(1);
}

function senddata($data) {
    http_response_code(200);
    $final = array();
    $final['data'] = $data;// Keep it safe for bool vs object list responses
    echo json_encode($final);
    exit(0);
}

function run_non_query($qs) {
    //echo $qs;
    //Returns a bool, true if success
    global $sql_loc;
    global $sql_db;
    global $sql_user;
    global $sql_password;

    $c = new mysqli(
        $sql_loc,
        $sql_user,
        $sql_password,
        $sql_db
    );
    //$c->connect();
    $c->query($qs);
    $c->close();
    return true;
}

function encode_for_sql($input,$active) {
    if ($active) {
        $input = str_replace("'","#SQ",$input);
        $input = str_replace("\"","#DQ",$input);
        $input = str_replace(";","#SC",$input);
        $input = str_replace(")","#CB",$input);
        $input = str_replace("\n","#NL",$input);
        $input = str_replace("\r","#CR",$input);
    }
    return $input;
}

function decode_for_sql($input) {
    $input = str_replace("#SQ","'",$input);
    $input = str_replace("#DQ","\"",$input);
    $input = str_replace("#SC",";",$input);
    $input = str_replace("#CB",")",$input);
    $input = str_replace("#NL","\n",$input);
    $input = str_replace("#CR","\r",$input);
    return $input;
}

function tvconv($iv) {

    if (is_numeric($iv)) {
        if (str_contains($iv,".")) {
            return (float)$iv;
        }
        return (int)$iv;
    } else {
        return decode_for_sql($iv);
    }
}

function run_query($qs) {
    //echo $qs;
    //Returns list of dicts or null if error.
    global $sql_loc;
    global $sql_db;
    global $sql_user;
    global $sql_password;

    $c = new mysqli(
        $sql_loc,
        $sql_user,
        $sql_password,
        $sql_db
    );
    //$c->connect();
    
    $result = $c->query($qs);
    $final = array();
    if (is_bool($result)) {
        return array();
    }
    if ($result->num_rows > 0) {
        // output data of each row
        while($row = $result->fetch_assoc()) {
            //array_push($final,$row);
            $t = array();
            foreach ($row as $key => $value) {
                $nv = tvconv($value);
                //echo $key . " : " . $nv. " : " . gettype($nv) . "\n";
                //For some reason, it isn't preserving the types
                $t[$key] = $nv;
            }
            array_push($final,$t);
        }
    } else {
        $final = array();
    }

    $c->close();
    return $final;

}

function run_query_p($query) {
    $final = array();
    foreach ($query as $st) {
        //echo $st;
        $final = array_merge($final,run_query($st));
    }
    return $final;
}

function run_non_query_p($query) {
    $iserror = false;
    foreach ($query as $st) {
        
        if (!run_non_query($st)) {
            $iserror = true;
            break;
        }
    }
    return $iserror;
}

function echo_and_run($query,$isExpectedToReturnData,$returnBool) {
    
    if ($isExpectedToReturnData) {
        $r = run_query_p($query);
        if (count($r) == 0 && $returnBool) {
            //echo "{\"error\":false,\"data\":false}"; 
            senddata(false);
            return;
        } else if (count($r) > 0 && $returnBool) {
            //echo "{\"error\":false,\"data\":true}";
            senddata(true);
            return;
        }
        if (is_null($r)) {
            //echo "{\"error\":true,\"data\":{}}";
            error(500,"Database Error");//Simulate empty data
        } else {
            //echo "{\"error\":false,\"data\":$final}";
            senddata($r);
        }

    } else {
        $r = !run_non_query_p($query);
        if (!$r) {
            //echo "{\"error\":true,\"data\":{}}";
            error(500,"Database Error");
        } else {
            //echo "{\"error\":false,\"data\":{}}";
            senddata(array());
        }
    }
}
header("content-type: application/json");

enum VarTypes {
    case String;
    case Number;
    case Bool;
}

class ApiAccept {
    //public $pp;
    public $vname;
    public $vtype;
    public $vdata;
    //public $p;
    function __construct($n,$t) {
        $this->vname = $n;
        if (str_contains($t,"bool")) {
            $this->vtype = VarTypes::Bool;
        } else if (str_contains($t,"num")) {
            $this->vtype = VarTypes::Number;
        } else {
            //str
            $this->vtype = VarTypes::String;
        }
    }
    function loadfrom($ar) {
        try {
            $raw = $ar[$this->vname];
            if ($this->vtype == VarTypes::Bool) {
                if ($raw) {
                    $this->vdata = 1;
                } else {
                    $this->vdata = 0;
                }
            } else if ($this->vtype == VarTypes::Number) {
                $this->vdata = floatval($raw);
            } else {
                $this->vdata = $raw;
            }
        } catch (Exception) {
            $this->vdata = "";
        }
    }
    function get() {
        return $this->vdata;
    }
}

class ApiAction {
    public $variables = array();
    public $actionname;
    public $statement;
    private $aq;
    private $retrbool;
    private $usesql;
    public $needsauth;
    public $privilege;
    public $preventattacks;

    function __construct($ar) {
        $this->actionname = $ar["action"];
        $this->statement = $ar["commands"];
        $this->aq = $ar["returns"];
        $this->usesql = $ar["usesql"];
        $this->preventattacks = $ar["encode"];
        foreach ($ar["accepts"] as $key => $value) {
            array_push($this->variables,new ApiAccept($key,$value));
        }
        $this->retrbool = $ar["sendbool"];

        $this->needsauth = $ar["auth"]["needsauth"];
        if ($this->needsauth) {
            $this->privilege = $ar["auth"]["level"];
        }
    }
    function processvars() {
        //$result = $this->statement;
        $final = array();
        foreach ($this->statement as $svalue) {
            $sresult = $svalue;
            foreach ($this->variables as $value) {
            
                $sresult = str_replace("$".$value->vname,encode_for_sql($value->get(),$this->preventattacks),$sresult);
            }
            array_push($final,$sresult);
        }
        
        return $final;
    }
    function execute() {
        global $LOG_HISTORY;
        //But first, log to history :)
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $processed_statement = $this->processvars();
        if ($LOG_HISTORY) {
            run_non_query("insert into history (source,action,statement,dt) values ('".$ip_address."','".$this->actionname."','".encode_for_sql(implode(';',$processed_statement),true)."',NOW())");
        }

        if ($this->usesql) {
            echo_and_run($processed_statement,$this->aq,$this->retrbool);
        } else {
            $ao = "";
            foreach ($processed_statement as $cmd) {
                $td = shell_exec($cmd);
                if (!$td) {
                    $ao = $ao . "No output produced OR error";
                } else {
                    $ao = $ao . $td;
                }
                
            }
            senddata($ao);
        }
    }
}

try {
    $fd = json_decode(file_get_contents("db.json"),true);

    $sql_loc = $fd['location'];
    $sql_db = $fd['dbname'];
    $sql_user = $fd['sqluser'];
    $sql_password = $fd['sqlpass'];
    $sql_port = $fd['port'];

    
    $body = json_decode(file_get_contents('php://input'),true);
    $calls = array();
    $file = fopen("api-format.json",'r');
    $rd = json_decode(fread($file,filesize("api-format.json")),true);
    fclose($file);

    foreach ($rd["api"] as $value) {
        $calls[$value["action"]] = new ApiAction($value);
    }

    $action2get = $body["_action"];//TODOTODOTOFO!!!!!!!!!!!!!!!!!!!! Decode json from body!!!!! Set as variables

    if ($calls[$action2get]->needsauth) {
        $username = $body["_username"];
        $password = $body["_password"];
        $is_valid_password = count(run_query("select * from accounts where username = '" . encode_for_sql($username,true) . "' and password = SHA2('" . encode_for_sql($password,true) . "',256) and isactive = true")) > 0 ;
        if (!$is_valid_password) {
            error(401,"Invalid account");
        }

        //Check if user has enough privilege
        $priv = run_query("select privilege from accounts where username = '" . encode_for_sql($username,true) . "'")[0]["privilege"];
        if ($calls[$action2get]->privilege > $priv) {
            error(403,"Insufficient Privilege");
        }
    }
    foreach ($calls as $key => $value) {
        if ($key == $action2get) {
            foreach ($value->variables as $kvv) {
                $kvv->loadfrom($body);
            }
            $value->execute();
            exit(0);
        }
    }

    error(400,"Invalid Request.");
    exit(1);
} catch (Exception $e) {
    error(500,$e->getMessage() . "\n" . $e->getTraceAsString());
    exit(1);
}
