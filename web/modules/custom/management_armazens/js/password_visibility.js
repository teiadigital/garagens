(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.managementPasswordVisibility = {
    attach(context) {
      once('management-password-visibility', 'input[type="password"]', context)
        .forEach((input) => {
          const alignToggle = () => {
            const wrapper = input.closest('.password-visibility-wrapper');
            if (wrapper) {
              wrapper.style.setProperty(
                '--password-input-height',
                `${input.getBoundingClientRect().height}px`,
              );
            }
          };

          // The public theme may already have added this control.
          if (input.dataset.passwordToggleReady === 'true') {
            window.requestAnimationFrame(alignToggle);
            return;
          }
          input.dataset.passwordToggleReady = 'true';

          const wrapper = document.createElement('span');
          wrapper.className = 'password-visibility-wrapper';
          input.parentNode.insertBefore(wrapper, input);
          wrapper.appendChild(input);

          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'password-visibility-toggle';
          button.setAttribute('aria-label', Drupal.t('Mostrar palavra-passe'));
          button.setAttribute('aria-pressed', 'false');
          button.innerHTML =
            '<svg class="password-eye password-eye--show" aria-hidden="true" viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>' +
            '<svg class="password-eye password-eye--hide" aria-hidden="true" viewBox="0 0 24 24"><path d="m3 3 18 18M10.6 6.1A9.8 9.8 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-3 3.7M6.3 6.3A16.4 16.4 0 0 0 2 12s3.5 6 10 6c1.6 0 3-.4 4.2-1M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';

          button.addEventListener('click', () => {
            const showPassword = input.type === 'password';
            input.type = showPassword ? 'text' : 'password';
            button.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
            button.setAttribute(
              'aria-label',
              showPassword
                ? Drupal.t('Ocultar palavra-passe')
                : Drupal.t('Mostrar palavra-passe'),
            );
            button.classList.toggle('is-visible', showPassword);
            input.focus();
          });

          wrapper.appendChild(button);
          window.requestAnimationFrame(alignToggle);
        });
    },
  };
})(Drupal, once);
