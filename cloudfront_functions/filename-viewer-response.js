function handler(event) {
    // NOTE: This example function is for a viewer request event trigger. 
    // Choose viewer request for event trigger when you associate this function with a distribution. 

    var response = event.response;
    
    //Add content-disposition if "filename" querystring exists.
    var req_qstr = event.request.querystring;
    var filename = "";

    //
    if(req_qstr.filename){
        if(req_qstr.filename.value){
            filename = req_qstr.filename.value;
            response.headers['content-disposition'] = {value: "attachment; filename=" + filename };
        }
    }
    //
    return response;
}