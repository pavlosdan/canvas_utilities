/**
 * @file
 * Canvas code-editor extension for adding icon props to a code component.
 *
 * Canvas 1.10's code-editor Props panel builds its "Type" menu from a
 * hard-coded array compiled into the Canvas bundle, and exposes no hook or
 * registry for adding entries. This extension provides the missing authoring
 * step from outside that menu: it edits the component's auto-save draft, which
 * is the same draft the code editor reads, so the prop appears in the Props
 * panel after a reload and is preserved on save.
 *
 * @see \Drupal\canvas_utilities_icons\Canvas\IconPropContract
 */
(function () {
  'use strict';

  var SCHEMA_REF = 'json-schema-definitions://canvas_utilities_icons.module/icon';
  var ENTITY_TYPE = 'js_component';

  var app = document.getElementById('app');
  var messageBox = document.getElementById('message');

  // A stable per-panel ID. Canvas rejects a write whose auto-save hashes are
  // stale, so a distinct ID makes a clash surface as a conflict rather than
  // silently overwriting whatever the editor last wrote.
  var clientInstanceId = 'canvas-utilities-icons-' + Math.random().toString(36).slice(2);

  var state = { componentId: null, props: {}, autoSaves: null, csrfToken: null };

  function message(text, kind) {
    if (!text) {
      messageBox.hidden = true;
      return;
    }
    messageBox.hidden = false;
    messageBox.className = 'msg ' + (kind || 'ok');
    messageBox.textContent = text;
  }

  /**
   * Reads the component being edited from the parent Canvas route.
   *
   * The extension iframe is same-origin, so the parent's path is readable. The
   * pattern is matched loosely because Canvas's router base path is
   * configurable.
   */
  function detectComponentId() {
    try {
      var path = window.parent.location.pathname;
      var match = path.match(/\/code-editor\/component\/([^/?#]+)/);
      return match ? decodeURIComponent(match[1]) : null;
    }
    catch (error) {
      return null;
    }
  }

  /**
   * Mirrors the camelCase machine name Canvas derives from a prop title.
   *
   * This has to agree with Canvas, not merely look similar: the code editor
   * discards the stored key and recomputes it from `title` every time the
   * component is saved, so a key written here that Canvas would derive
   * differently silently renames the prop on the next save.
   *
   * Word splitting follows lodash's `camelCase`, which breaks on
   * non-alphanumerics, lower-to-upper transitions, acronym boundaries, and
   * digit/letter boundaries.
   *
   * @see getPropMachineName() in ui/src/features/code-editor/utils/utils.ts
   *   in the canvas module.
   */
  function machineName(value) {
    var words = String(value)
      .replace(/([A-Z]+)([A-Z][a-z])/g, '$1 $2')
      .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
      .replace(/([A-Za-z])([0-9])/g, '$1 $2')
      .replace(/([0-9])([A-Za-z])/g, '$1 $2')
      .match(/[A-Za-z0-9]+/g);
    if (!words || !words.length) {
      return '';
    }
    return words
      .map(function (word, index) {
        var lower = word.toLowerCase();
        return index === 0 ? lower : lower.charAt(0).toUpperCase() + lower.slice(1);
      })
      .join('');
  }

  function isIconProp(prop) {
    return prop && prop.$ref === SCHEMA_REF;
  }

  function request(url, options) {
    return fetch(url, Object.assign({ credentials: 'same-origin' }, options)).then(function (response) {
      if (!response.ok) {
        return response
          .json()
          .catch(function () {
            return null;
          })
          .then(function (body) {
            var detail = body && (body.message || body.error || (body.errors && body.errors[0] && body.errors[0].detail));
            throw new Error(detail || 'Request failed with status ' + response.status + '.');
          });
      }
      return response.status === 204 ? null : response.json();
    });
  }

  function draftUrl() {
    return '/canvas/api/v0/config/auto-save/' + ENTITY_TYPE + '/' + encodeURIComponent(state.componentId);
  }

  function loadDraft() {
    return request(draftUrl(), { headers: { Accept: 'application/json' } }).then(function (result) {
      state.props = (result.data && result.data.props) || {};
      // An empty props map serializes as [] rather than {}.
      if (Array.isArray(state.props)) {
        state.props = {};
      }
      state.autoSaves = result.autoSaves || {};
      state.required = (result.data && result.data.required) || [];
      if (Array.isArray(state.required) === false) {
        state.required = [];
      }
    });
  }

  function saveProps(props) {
    // Re-read immediately before writing so the hashes sent are current.
    return loadDraft()
      .then(function () {
        return request(draftUrl(), {
          method: 'PATCH',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-Token': state.csrfToken,
          },
          body: JSON.stringify({
            data: { props: props, required: state.required },
            autoSaves: state.autoSaves,
            clientInstanceId: clientInstanceId,
          }),
        });
      })
      .then(loadDraft);
  }

  function addIconProp(name) {
    var key = machineName(name);
    if (!key) {
      throw new Error('Enter a prop name using letters or numbers.');
    }
    if (Object.prototype.hasOwnProperty.call(state.props, key)) {
      throw new Error('This component already has a prop named "' + key + '".');
    }
    var props = {};
    Object.keys(state.props).forEach(function (existing) {
      props[existing] = state.props[existing];
    });
    props[key] = {
      title: String(name).trim(),
      type: 'string',
      $ref: SCHEMA_REF,
    };
    return saveProps(props);
  }

  function removeProp(key) {
    var props = {};
    Object.keys(state.props).forEach(function (existing) {
      if (existing !== key) {
        props[existing] = state.props[existing];
      }
    });
    state.required = state.required.filter(function (name) {
      return name !== key;
    });
    return saveProps(props);
  }

  function element(tag, attributes, children) {
    var node = document.createElement(tag);
    Object.keys(attributes || {}).forEach(function (name) {
      if (name === 'text') {
        node.textContent = attributes[name];
      }
      else if (name === 'html') {
        node.innerHTML = attributes[name];
      }
      else if (name.slice(0, 2) === 'on') {
        node.addEventListener(name.slice(2), attributes[name]);
      }
      else {
        node.setAttribute(name, attributes[name]);
      }
    });
    (children || []).forEach(function (child) {
      node.appendChild(child);
    });
    return node;
  }

  function renderPropsCard() {
    var names = Object.keys(state.props);
    var card = element('div', { class: 'card' }, [element('h2', { text: 'Props on this component' })]);

    if (!names.length) {
      card.appendChild(element('p', { class: 'empty', text: 'This component has no props yet.' }));
      return card;
    }

    var body = element('tbody');
    names.forEach(function (key) {
      var prop = state.props[key];
      var icon = isIconProp(prop);
      var typeCell = element('td');
      if (icon) {
        typeCell.appendChild(element('span', { class: 'tag', text: 'Icon' }));
      }
      else {
        typeCell.appendChild(document.createTextNode(prop.type || 'unknown'));
      }

      var actionCell = element('td');
      if (icon) {
        actionCell.appendChild(
          element('button', {
            type: 'button',
            class: 'linky',
            text: 'Remove',
            onclick: function () {
              act(function () {
                return removeProp(key);
              }, 'Removed the "' + key + '" prop. Reload the code editor now — until you do, it cannot save.');
            },
          }),
        );
      }

      body.appendChild(
        element('tr', {}, [
          element('td', {}, [element('code', { text: key })]),
          typeCell,
          actionCell,
        ]),
      );
    });

    card.appendChild(
      element('table', {}, [
        element('thead', {}, [
          element('tr', {}, [
            element('th', { text: 'Name' }),
            element('th', { text: 'Type' }),
            element('th', { text: '' }),
          ]),
        ]),
        body,
      ]),
    );
    return card;
  }

  function renderAddCard() {
    var input = element('input', { type: 'text', id: 'prop-name', value: 'icon', placeholder: 'icon' });
    var preview = element('p', { class: 'empty', id: 'prop-preview' });

    function updatePreview() {
      var key = machineName(input.value);
      preview.textContent = key
        ? 'Your component receives this as ' + key + '.'
        : 'Enter a name using letters or numbers.';
    }
    input.addEventListener('input', updatePreview);
    updatePreview();

    var button = element('button', {
      type: 'button',
      text: 'Add icon prop',
      onclick: function () {
        var name = input.value.trim();
        act(function () {
          return addIconProp(name);
        }, 'Added the "' + machineName(name) + '" prop. Reload the code editor now — until you do, it cannot save.');
      },
    });

    return element('div', { class: 'card' }, [
      element('h2', { text: 'Add an icon prop' }),
      element('div', { class: 'row' }, [
        element('div', {}, [element('label', { for: 'prop-name', text: 'Prop name' }), input]),
        element('div', { style: 'flex:0 0 auto' }, [button]),
      ]),
      preview,
    ]);
  }

  function renderReloadCard() {
    return element('div', { class: 'card' }, [
      element('h2', { text: 'After changing props' }),
      element('p', {
        class: 'empty',
        text:
          'The code editor holds its own copy of the props while it is open. Once you add or remove a prop here, ' +
          'Canvas rejects the editor’s saves until it is reloaded, so reload it before making further code changes.',
      }),
      element('button', {
        type: 'button',
        class: 'secondary',
        text: 'Reload the code editor',
        onclick: function () {
          window.parent.location.reload();
        },
      }),
    ]);
  }

  /**
   * Explains why icon props are managed here rather than in the Props panel.
   */
  function renderExplainerCard() {
    return element('div', { class: 'card' }, [
      element('h2', { text: 'Why here?' }),
      element('p', {
        class: 'empty',
        text:
          'Canvas builds its Props panel from a fixed list of prop types and hides any prop it does not recognize, ' +
          'so icon props are managed from this panel instead. They are saved with the component as normal, and ' +
          'editors get the icon picker in the component’s settings once it is placed on a page.',
      }),
    ]);
  }

  function render() {
    app.textContent = '';
    app.appendChild(renderPropsCard());
    app.appendChild(renderAddCard());
    app.appendChild(renderReloadCard());
    app.appendChild(renderExplainerCard());
  }

  /**
   * Runs a mutation, then re-renders and reports the outcome.
   */
  function act(operation, successMessage) {
    message('');
    var buttons = app.querySelectorAll('button');
    Array.prototype.forEach.call(buttons, function (button) {
      button.disabled = true;
    });
    Promise.resolve()
      .then(operation)
      .then(function () {
        render();
        message(successMessage, 'ok');
      })
      .catch(function (error) {
        render();
        message(error.message, 'err');
      });
  }

  function start() {
    state.componentId = detectComponentId();
    if (!state.componentId) {
      app.appendChild(
        element('div', { class: 'card' }, [
          element('p', {
            class: 'empty',
            text: 'Open a code component in the code editor, then reopen this panel.',
          }),
        ]),
      );
      return;
    }

    // The token endpoint answers in plain text, so it bypasses request().
    fetch('/session/token', { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Could not obtain a CSRF token.');
        }
        return response.text();
      })
      .then(function (token) {
        state.csrfToken = token;
        return loadDraft();
      })
      .then(render)
      .catch(function (error) {
        message(error.message, 'err');
      });
  }

  start();
})();
