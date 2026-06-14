function confirmDelete(msg, url) {
    if (confirm(msg)) {
        window.location.href = url;
    }
}
$(document).ready(function() {
    $('.select2-basic').select2(
        {
            "width": "100%"
        }
    );
});
