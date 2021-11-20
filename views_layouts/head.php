
<!-- /themes/MyTheme/views/layouts/head.php -->
<script>
    (()=>{
        'use strict';
        //add filename query
        let scan_anchor = () => {
            $("a[data-file-name]").not(".fnq-added").each((i,el)=>{
                let file_name = $(el).data("file-name");
                let href = $(el).attr("href");
                $(el).attr("href", href + "&filename=" + file_name)
                $(el).addClass("fnq-added");
            });
        }
        //polling
        setInterval(scan_anchor, 200);
    })();
</script>
<!-- end /themes/MyTheme/views/layouts/head.php -->
