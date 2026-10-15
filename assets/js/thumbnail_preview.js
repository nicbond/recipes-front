import $ from 'jquery';

function initThumbnailPreview() {
    const $input = $('input[type="file"]');
    if ($input.length === 0) return;

    $input.off('change.thumbnail').on('change.thumbnail', function () {
        const file = this.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            $('#thumbnail-preview-container').html(
                `<img id="thumbnail-preview"
                      src="${e.target.result}"
                      alt="Aperçu de la recette"
                      class="img-fluid rounded shadow-sm w-100 modal-thumbnail-preview">`
            );
        };
        reader.readAsDataURL(file);
    });
}

$(document).ready(initThumbnailPreview);
document.addEventListener('turbo:load', initThumbnailPreview);
document.addEventListener('turbo:frame-load', initThumbnailPreview);
