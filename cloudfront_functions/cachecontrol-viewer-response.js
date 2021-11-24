function handler(event) {

    var response = event.response;
    var headers = response.headers;
    
    //Add chache-control, 60*60*24*7
    headers['cache-control'] = {value: 'public, max-age=604800, immutable;'};
    
    //
    return response;
}