document.addEventListener('DOMContentLoaded', () => {
  function validateDataTargets(el, options = {}) {
    const { execute = false, onValid } = options;
    const results = [];
    const targetSel = el.dataset.targetId; // e.g. "#selected-id"
    const valueSel = el.dataset.value; // e.g. "#user-id"
    const targetNode = targetSel ? document.querySelector(targetSel) : null;
    const valueNode = valueSel ? document.querySelector(valueSel) : null;

    results.push({ el, target: targetNode, valueEl: valueNode });

    if (!targetNode || !valueNode) {
      console.warn('Data‑attribute validation failed:', {
        element: el,
        targetSel,
        valueSel,
        targetNode,
        valueNode,
      });
      return false;
    }

    // Optional immediate action
    if (execute && typeof onValid === 'function') {
      onValid(targetNode, valueNode, el.dataset.action);
    }

    return false;
  }

  document.querySelector('.page').addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    const clicked = event.target.closest('.bazar-api');
    if (clicked) {
      validateDataTargets(clicked, {
        execute: true,
        onValid: (targetEl, valueEl, action) => {
          if (action === 'add') {
            console.log('coucou');
          }
        },
      });
    }
    return false;
  });
});
