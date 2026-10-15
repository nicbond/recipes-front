import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'icon'];

    toggle() {
        const isVisible = this.inputTarget.type === 'password';

        this.inputTarget.type = isVisible ? 'text' : 'password';

        this.iconTarget.classList.toggle('fa-eye-slash', !isVisible);
        this.iconTarget.classList.toggle('fa-eye', isVisible);

        this.element.querySelector('.password-field__toggle').title =
            isVisible
                ? 'Masquer le mot de passe'
                : 'Afficher le mot de passe';
    }
}