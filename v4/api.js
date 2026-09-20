//This is EPAPI 4

//SETTING - Should we print information in the console?
const VERBOSE = true;

// This version just has it set up so that it passes a blank password which does not get read, because everything is needsauth=false. Technically it is a clone with EPAPI 3S that doesn't have full authentication.
var username = ""
var password = ""
var apipath = "/api/api.php"

class APIResponse {
    constructor(raw,status) {
        //if (raw.includes("\n#0")) {
            //Error
        //    raw = raw.split("\n#0")[0]
        //}
        var r;
        try {
            r = JSON.parse(raw)
        } catch {
            r = {data:raw}//Fall back to plain text
        }
        this.iserror = status != 200//Not 200 = error
        if (this.iserror) {
            this.errorMessage = r.message
            this.data = ""
        } else {
            this.errorMessage = ""
            this.data = r.data
        }

    }
}


//These three functions are usually found in utils.js, but in order to keep the libraries seperate, they have been copied here.
function _round(n,d) {
    return Math.round(n * Math.pow(10,d)) / Math.pow(10,d)
}

function _parseSize(s) {
    if (s > 2000000000) {
        return _round(s/1000000000,2) + " GB"
    } else if (s > 2000000) {
        return _round(s/1000000,2) + " MB"
    } else if (s > 2000) {
        return _round(s/1000,2) + " KB"
    } else {
        return Math.round(s) + " bytes"
    }
}

function _parseTime(t) {
    if (t < 2000) {
        return t + " ms"
    } else if (t < 60000) {
        return _round(t/1000,1) + " s"
    } else {
        return Math.floor(t/60000) + ":" + _round(t % 60000,1)
    }
}

function call(action,variables,callback) {
    variables["_action"] = action
    try {
        variables["_username"] = username
        variables["_password"] = password
    } catch {
        
    }
    var stime = Date.now()
    let rawvars = JSON.stringify(variables)
    //alert(rawvars)
    fetch(apipath, {
        method: 'POST',
        headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
        },
        body: rawvars
    }).then(
        function(r) {
            let _s = r.status
            r.text().then(function(t) {
                t = t.replaceAll("#SQ","'").replaceAll("#DQ",'\\"')
                var etime = Date.now()
                var totalms = etime - stime
                var datatransferlength = t.length + rawvars.length
                var bytespersecond = datatransferlength / (totalms / 1000)
                if (VERBOSE) {
                    console.log(`${action} [${rawvars}] -> ${t} (${_s}) | ${_parseSize(datatransferlength)} in ${_parseTime(totalms)} (${_parseSize(bytespersecond)}/s)`)
                }
                //alert(t)
                callback(new APIResponse(t,_s))
            })
        }
    )
}
