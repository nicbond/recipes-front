import * as bootstrap from 'bootstrap';

document.addEventListener('turbo:frame-load', function(e) {
    if (e.target.id === 'modal') {
        const modalEl = document.getElementById('turbo-modal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
});