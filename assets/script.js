document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector(".mbp-form");
    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();

            const formData = new FormData(form);
            fetch(mbp_ajax.ajax_url, {
                method: "POST",
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) form.reset();
            })
            .catch(error => console.error("Erreur :", error));
        });
    }
});
