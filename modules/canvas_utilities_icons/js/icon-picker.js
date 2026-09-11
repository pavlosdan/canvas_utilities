(function iconPickerBehavior(Drupal, once) {
  Drupal.behaviors.canvasUtilitiesIconPicker = {
    attach(context) {
      once(
        'canvas-utilities-icon-picker',
        '.js-canvas-utilities-icon-picker',
        context,
      ).forEach((input) => {
        const theme = input.dataset.canvasUtilitiesTheme;

        // The libraries rendered with the element are the starting point. They
        // deliberately do not come from drupalSettings: Drupal merges the
        // settings of every AJAX response into the existing ones recursively,
        // so a list that has grown shorter keeps its removed entries.
        let libraries = [];
        try {
          libraries = JSON.parse(
            input.dataset.canvasUtilitiesIconLibraries || '[]',
          );
        } catch (error) {
          libraries = [];
        }
        let icons = [];

        const picker = document.createElement('div');
        picker.className = 'canvas-utilities-icon-picker';
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'canvas-utilities-icon-picker__trigger';
        const currentPreview = document.createElement('span');
        currentPreview.className = 'canvas-utilities-icon-picker__preview';
        const currentLabel = document.createElement('span');
        currentLabel.className = 'canvas-utilities-icon-picker__label';
        const action = document.createElement('span');
        action.className = 'canvas-utilities-icon-picker__action';
        action.textContent = 'Browse';
        trigger.append(currentPreview, currentLabel, action);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'canvas-utilities-icon-picker__remove';
        remove.setAttribute('aria-label', 'Remove icon');
        remove.title = 'Remove icon';
        remove.textContent = '×';

        const dialog = document.createElement('dialog');
        dialog.className = 'canvas-utilities-icon-picker__dialog';
        const header = document.createElement('header');
        const title = document.createElement('h3');
        title.textContent = 'Choose an icon';
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'canvas-utilities-icon-picker__close';
        close.setAttribute('aria-label', 'Close icon picker');
        close.textContent = '×';
        header.append(title, close);

        const filters = document.createElement('div');
        filters.className = 'canvas-utilities-icon-picker__filters';
        const search = document.createElement('input');
        search.type = 'search';
        search.placeholder = 'Search icons';
        search.setAttribute('aria-label', 'Search icons');
        const librarySelect = document.createElement('select');
        librarySelect.setAttribute('aria-label', 'Filter by icon library');
        const allLibraries = document.createElement('option');
        allLibraries.value = '';
        allLibraries.textContent = 'All libraries';
        librarySelect.append(allLibraries);
        filters.append(search, librarySelect);

        const applyLibraries = (list) => {
          libraries = list;
          icons = libraries.flatMap((library) =>
            library.icons.map((icon) => ({ ...icon, library: library.label })),
          );
          const previous = librarySelect.value;
          librarySelect.replaceChildren(allLibraries);
          libraries.forEach((library) => {
            const option = document.createElement('option');
            option.value = library.label;
            option.textContent = library.label;
            librarySelect.append(option);
          });
          librarySelect.value = libraries.some((l) => l.label === previous)
            ? previous
            : '';
        };

        // Canvas keeps a component's settings form in its own client-side
        // cache, so the markup rendered here can outlive changes made in the
        // Design system: a library deleted afterwards would still be offered
        // until the whole page was reloaded. Re-reading the libraries as the
        // picker opens keeps the choices current. If the request fails, for
        // example because the account cannot read the API, the options
        // rendered with the element are kept.
        const refreshLibraries = async () => {
          if (!theme) {
            return;
          }
          try {
            const response = await fetch(
              `/canvas-utilities/api/v1/icons/${encodeURIComponent(theme)}`,
              {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
              },
            );
            if (!response.ok) {
              return;
            }
            const { data } = await response.json();
            if (!Array.isArray(data)) {
              return;
            }
            applyLibraries(
              data
                .filter((library) => library.status)
                .map((library) => ({
                  id: library.id,
                  label: library.label,
                  icons: (library.icons || []).map((icon) => ({
                    id: icon.id,
                    label: icon.label,
                    group: icon.group,
                    value: icon.url,
                  })),
                })),
            );
          } catch (error) {
            // Keep the options that were rendered with the element.
          }
        };

        const results = document.createElement('div');
        results.className = 'canvas-utilities-icon-picker__results';
        results.setAttribute('role', 'list');
        const status = document.createElement('p');
        status.className = 'canvas-utilities-icon-picker__status';
        status.setAttribute('aria-live', 'polite');

        const setValue = (value) => {
          // Canvas controls this input in React. Use the native setter so its
          // value tracker sees the change before the input event is emitted.
          const valueSetter = Object.getOwnPropertyDescriptor(
            window.HTMLInputElement.prototype,
            'value',
          ).set;
          valueSetter.call(input, value);
          updateCurrent();
          input.dispatchEvent(new Event('input', { bubbles: true }));
        };
        const selectIcon = (icon) => {
          setValue(icon.value);
          dialog.close();
        };
        const renderResults = () => {
          const query = search.value.trim().toLocaleLowerCase();
          const selectedLibrary = librarySelect.value;
          const matches = icons.filter(
            (icon) =>
              (!selectedLibrary || icon.library === selectedLibrary) &&
              (!query ||
                `${icon.label} ${icon.id} ${icon.group}`
                  .toLocaleLowerCase()
                  .includes(query)),
          );
          results.replaceChildren();
          matches.forEach((icon) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'canvas-utilities-icon-picker__item';
            button.setAttribute('role', 'listitem');
            button.title = `${icon.library}: ${icon.label}`;
            const image = document.createElement('img');
            image.src = icon.value;
            image.alt = '';
            image.loading = 'lazy';
            const label = document.createElement('span');
            label.textContent = icon.label;
            button.append(image, label);
            button.addEventListener('click', () => selectIcon(icon));
            results.append(button);
          });
          status.textContent = `${matches.length} icon${matches.length === 1 ? '' : 's'}`;
        };

        dialog.append(header, filters, status, results);
        picker.append(trigger, remove, dialog);
        trigger.addEventListener('click', async () => {
          renderResults();
          dialog.showModal();
          search.focus();
          await refreshLibraries();
          renderResults();
          updateCurrent();
        });
        close.addEventListener('click', () => dialog.close());
        remove.addEventListener('click', () => {
          setValue('');
          trigger.focus();
        });
        dialog.addEventListener('click', (event) => {
          if (event.target === dialog) {
            dialog.close();
          }
        });
        search.addEventListener('input', renderResults);
        librarySelect.addEventListener('change', renderResults);

        const updateCurrent = () => {
          const selected = icons.find((icon) => icon.value === input.value);
          currentPreview.replaceChildren();
          if (selected) {
            const image = document.createElement('img');
            image.src = selected.value;
            image.alt = '';
            currentPreview.append(image);
            currentLabel.textContent = selected.label;
            trigger.title = `${selected.library}: ${selected.label}`;
          } else {
            currentLabel.textContent = input.value
              ? 'Unavailable icon'
              : 'Choose an icon';
            trigger.title = currentLabel.textContent;
          }
          remove.hidden = input.value === '';
        };

        applyLibraries(libraries);
        const insertionPoint = input.parentElement || input;
        if (insertionPoint !== input) {
          insertionPoint.classList.add(
            'canvas-utilities-icon-picker__value-wrapper',
          );
        }
        insertionPoint.before(picker);
        input.addEventListener('change', updateCurrent);
        updateCurrent();
      });
    },
  };
})(Drupal, once);
