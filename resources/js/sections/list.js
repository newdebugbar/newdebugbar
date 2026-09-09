/** Filters inspector rows without letting host styles reveal hidden results. */
export function filterList(items, { selected, key, matches }) {
  let visible = 0;
  let firstVisible = null;
  let selectedVisible = false;

  for (const item of items) {
    const show = matches(item);
    item.hidden = !show;

    if (show) {
      item.style?.removeProperty?.('display');
      const id = key(item);
      firstVisible ??= id;
      selectedVisible ||= id === selected;
      visible++;
    } else {
      item.style?.setProperty?.('display', 'none', 'important');
    }
  }

  return { visible, firstVisible, selectedVisible };
}
