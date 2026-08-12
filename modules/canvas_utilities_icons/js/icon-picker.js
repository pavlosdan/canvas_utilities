(function iconPickerBehavior(Drupal, once, drupalSettings) {
  Drupal.behaviors.canvasUtilitiesIconPicker = {
    attach(context) {
      once(
        'canvas-utilities-icon-picker',
        '.js-canvas-utilities-icon-picker',
        context,
      ).forEach((input) => {
        const theme = input.dataset.canvasUtilitiesTheme;
        const libraries =
          drupalSettings.canvasUtilitiesIcons?.themes?.[theme] || [];
        const icons = libraries.flatMap((library) =>
          library.icons.map((icon) => ({ ...icon, library: library.label })),
        );

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
        libraries.forEach((library) => {
          const option = document.createElement('option');
          option.value = library.label;
          option.textContent = library.label;
          librarySelect.append(option);
        });
        filters.append(search, librarySelect);

        const results = document.createElement('div');
        results.className = 'canvas-utilities-icon-picker__results';
        results.setAttribute('role', 'list');
        const status = document.createElement('p');
        status.className = 'canvas-utilities-icon-picker__status';
        status.setAttribute('aria-live', 'polite');

        const selectIcon = (icon) => {
          input.value = icon.value;
          updateCurrent();
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
        picker.append(trigger, dialog);
        trigger.addEventListener('click', () => {
          renderResults();
          dialog.showModal();
          search.focus();
        });
        close.addEventListener('click', () => dialog.close());
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
        };

        const insertionPoint = input.parentElement || input;
        insertionPoint.before(picker);
        input.addEventListener('change', updateCurrent);
        updateCurrent();
      });
    },
  };
})(Drupal, once, drupalSettings);
