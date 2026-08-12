(function colorPickerBehavior(Drupal, once, drupalSettings) {
  Drupal.behaviors.canvasUtilitiesColorPicker = {
    attach(context) {
      once(
        'canvas-utilities-color-picker',
        '.js-canvas-utilities-color-picker',
        context,
      ).forEach((input) => {
        const theme = input.dataset.canvasUtilitiesTheme;
        const colors =
          drupalSettings.canvasUtilitiesPalette?.themes?.[theme] || [];
        const allowCustom =
          input.dataset.canvasUtilitiesAllowCustom === 'true';
        const picker = document.createElement('div');
        picker.className = 'canvas-utilities-color-picker';
        let customInput = null;

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'canvas-utilities-color-picker__trigger';
        trigger.setAttribute('aria-expanded', 'false');

        const currentSwatch = document.createElement('span');
        currentSwatch.className = 'canvas-utilities-color-picker__current-swatch';
        const currentLabel = document.createElement('span');
        currentLabel.className = 'canvas-utilities-color-picker__current-label';
        const chevron = document.createElement('span');
        chevron.setAttribute('aria-hidden', 'true');
        chevron.textContent = '▾';
        trigger.append(currentSwatch, currentLabel, chevron);

        const panel = document.createElement('div');
        panel.className = 'canvas-utilities-color-picker__panel';
        panel.hidden = true;

        const grouped = new Map();
        colors.forEach((color) => {
          const paletteColors = grouped.get(color.palette) || [];
          paletteColors.push(color);
          grouped.set(color.palette, paletteColors);
        });
        if (colors.length === 0) {
          const empty = document.createElement('p');
          empty.className = 'canvas-utilities-color-picker__empty';
          empty.textContent = 'No palette colors are available for this theme.';
          panel.append(empty);
        }
        grouped.forEach((paletteColors, palette) => {
          const group = document.createElement('section');
          const heading = document.createElement('h4');
          heading.textContent = palette;
          const grid = document.createElement('div');
          grid.className = 'canvas-utilities-color-picker__grid';
          paletteColors.forEach((color) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'canvas-utilities-color-picker__swatch';
            button.dataset.value = color.value;
            button.title = `${color.label} — ${color.preview}`;
            button.setAttribute('aria-label', `${color.label}, ${color.preview}`);
            button.style.setProperty('--canvas-utilities-swatch', color.preview);
            button.addEventListener('click', () => {
              input.value = color.value;
              panel.hidden = true;
              trigger.setAttribute('aria-expanded', 'false');
              updateCurrent();
            });
            grid.append(button);
          });
          group.append(heading, grid);
          panel.append(group);
        });

        trigger.addEventListener('click', () => {
          panel.hidden = !panel.hidden;
          trigger.setAttribute('aria-expanded', String(!panel.hidden));
        });

        const updateCurrent = () => {
          const selected = colors.find((color) => color.value === input.value);
          const preview = selected?.preview || input.value || 'transparent';
          currentSwatch.style.setProperty('--canvas-utilities-swatch', preview);
          currentLabel.textContent =
            selected?.label || input.value || 'Choose a color';
          trigger.title = selected
            ? `${selected.palette}: ${selected.label}`
            : input.value || 'Choose a color';
          const nativeColor = preview.match(/^#([0-9a-f]{6})$/i);
          if (customInput && nativeColor) {
            customInput.value = preview;
          }
        };

        picker.append(trigger, panel);
        const insertionPoint = input.parentElement || input;
        insertionPoint.before(picker);
        if (allowCustom) {
          const customControl = document.createElement('div');
          customControl.className =
            'canvas-utilities-color-picker__custom-control';
          const customLabel = document.createElement('label');
          customLabel.className = 'canvas-utilities-color-picker__custom-label';
          customLabel.textContent = 'Custom color';
          customInput = document.createElement('input');
          customInput.type = 'color';
          customInput.id = `${input.id}--native-color`;
          customInput.className =
            'canvas-utilities-color-picker__custom-input';
          customInput.setAttribute('aria-label', 'Choose a custom color');
          customLabel.htmlFor = customInput.id;
          customInput.addEventListener('input', () => {
            input.value = customInput.value;
            updateCurrent();
          });
          customControl.append(customLabel, customInput);
          insertionPoint.before(customControl);
        }
        input.addEventListener('input', updateCurrent);
        input.addEventListener('change', updateCurrent);
        updateCurrent();
      });
    },
  };
})(Drupal, once, drupalSettings);
