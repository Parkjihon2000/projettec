document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    
    form.addEventListener('submit', function(event) {
        event.preventDefault(); // Empêcher la soumission normale du formulaire

        const formData = new FormData(form);
        
        fetch('projet.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            document.querySelector('tbody').innerHTML = data; // Mettre à jour le tableau avec les projets filtrés
        });
    });
});   