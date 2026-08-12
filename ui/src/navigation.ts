export function getRouteFromHash(): string {
  return window.location.hash.replace(/^#\/?/, '').replace(/\/$/, '');
}

export function navigate(route: string): void {
  const normalized = route.replace(/^\//, '');
  window.location.hash = normalized ? `/${normalized}` : '/';
  window.parent.postMessage(
    {
      type: 'canvas:navigate',
      subPath: normalized,
    },
    window.location.origin,
  );
}

