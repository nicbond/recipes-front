function initCharacterCounter() {
    const textarea = document.querySelector('#recipe_content');

    if (!textarea || textarea.parentNode.querySelector('.js-counter-added')) return;

    const counter = document.createElement('div');
    counter.classList.add('form-text', 'text-muted', 'text-end', 'js-counter-added');
    textarea.parentNode.appendChild(counter);

    function updateCounter() {
        const max = textarea.getAttribute('maxlength');
        if (max) {
            counter.textContent = textarea.value.length + ' / ' + max + ' caractères';
            counter.classList.toggle('text-danger', textarea.value.length >= max);
        } else {
            counter.textContent = textarea.value.length + ' caractère(s) saisi(s)';
        }
    }

    textarea.addEventListener('input', updateCounter);
    updateCounter();
}

// 1. (First visit / Refresher)
document.addEventListener('DOMContentLoaded', function () {
    initCharacterCounter();
});

// 2. (Page change / "New" page)
document.addEventListener('turbo:load', function () {
    initCharacterCounter();
});

// 3.(Modal opening / "Edit" page)
document.addEventListener('turbo:frame-load', function () {
    initCharacterCounter();
});

