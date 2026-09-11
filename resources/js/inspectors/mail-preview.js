const MAIL_PREVIEW_WIDTHS = {
  desktop: 1024,
  mobile: 375,
};

/** Owns mail-preview inspector state and interactions. */
export function createMailPreview(context) {
  const { browser, shell, profileId } = context;
  return {
    mailPreviewFormat: 'html',
    mailPreviewViewport: 'desktop',
    mailPreviewFrameCleanup: null,

    get mailPreviewActive() {
      return (
        !this.destroyed &&
        profileId === shell.summary.id &&
        shell.inspectorOpen &&
        shell.barVisible &&
        shell.selected === 'mail' &&
        this.mailDetailTab === 'preview'
      );
    },

    setMailPreviewFormat(format) {
      if (!['html', 'text'].includes(format)) return;

      const message = this.selectedMailMessage;
      if (format === 'html' && !message?.has_html) return;
      if (format === 'text' && !message?.has_text) return;

      this.mailPreviewFormat = format;
      this.$nextTick?.(() => {
        const frame = this.$refs?.mailPreviewFrame;
        if (!frame) return;

        frame.style.setProperty('height', '20rem', 'important');
        this.resizeMailPreviewFrame(frame);
      });
    },

    setMailPreviewViewport(viewport) {
      if (!['desktop', 'mobile'].includes(viewport)) return;
      if (this.mailPreviewFormat === 'text') return;

      this.mailPreviewViewport = viewport;
      this.$nextTick?.(() => {
        const frame = this.$refs?.mailPreviewFrame;
        if (!frame) return;

        frame.style.setProperty('height', '20rem', 'important');
        this.resizeMailPreviewFrame(frame);
      });
    },

    connectMailPreviewFrame(frame) {
      if (!this.mailPreviewActive || frame?.isConnected === false) return;
      if (typeof HTMLIFrameElement === 'undefined' || !(frame instanceof HTMLIFrameElement)) return;

      this.mailPreviewFrameCleanup?.();
      frame.__newDebugBarMailPreviewCleanup?.();

      const canvas = frame.closest('[data-ndb-mail-preview-canvas]');
      let connected = true;
      let layoutScheduled = false;
      const scheduleLayout = () => {
        if (!connected || layoutScheduled) return;

        layoutScheduled = true;
        browser.nextFrame(() => {
          layoutScheduled = false;
          if (connected) this.layoutMailPreviewFrame(frame);
        });
      };

      const handlePreviewMessage = (event) => {
        if (event.source !== frame.contentWindow) return;

        if (event.data?.type === 'newdebugbar:mail-preview-scroll' && Number.isFinite(event.data.deltaY)) {
          const detail = frame.closest('[data-ndb-mail-detail]');
          if (!detail) return;

          const multiplier =
            event.data.deltaMode === 1 ? 16 : event.data.deltaMode === 2 ? detail.clientHeight : 1;
          detail.scrollBy({ top: event.data.deltaY * multiplier });

          return;
        }

        if (event.data?.type !== 'newdebugbar:mail-preview-height' || !Number.isFinite(event.data.height))
          return;

        const height = Math.min(100_000, Math.max(320, Math.ceil(event.data.height)));
        frame.style.setProperty('height', `${height}px`, 'important');
        this.layoutMailPreviewFrame(frame);
      };

      window.addEventListener('message', handlePreviewMessage);
      if (canvas && typeof ResizeObserver === 'function') {
        const observer = new ResizeObserver(scheduleLayout);
        observer.observe(canvas);
        frame.__newDebugBarMailPreviewCanvasObserver = observer;
      }

      const cleanup = () => {
        connected = false;
        window.removeEventListener('message', handlePreviewMessage);
        frame.__newDebugBarMailPreviewObserver?.disconnect?.();
        frame.__newDebugBarMailPreviewCanvasObserver?.disconnect?.();
      };
      frame.__newDebugBarMailPreviewCleanup = cleanup;
      this.mailPreviewFrameCleanup = cleanup;
      scheduleLayout();
    },

    layoutMailPreviewFrame(frame) {
      if (typeof HTMLIFrameElement === 'undefined' || !(frame instanceof HTMLIFrameElement)) return;

      const canvas = frame.closest('[data-ndb-mail-preview-canvas]');
      const availableWidth = canvas?.clientWidth ?? 0;
      if (!canvas || availableWidth <= 0) return;

      const fixedWidth =
        this.mailPreviewFormat === 'html' ? MAIL_PREVIEW_WIDTHS[this.mailPreviewViewport] : null;
      const frameWidth = fixedWidth ?? availableWidth;
      const scale = fixedWidth ? Math.min(1, availableWidth / fixedWidth) : 1;

      frame.style.setProperty('width', `${frameWidth}px`, 'important');
      frame.style.setProperty('transform', `translateX(-50%) scale(${scale})`, 'important');

      const frameHeight = frame.offsetHeight;
      if (frameHeight > 0) {
        canvas.style.setProperty('height', `${Math.ceil(frameHeight * scale)}px`, 'important');
      }
    },

    resizeMailPreviewFrame(frame) {
      if (!this.mailPreviewActive || frame?.isConnected === false) return;
      if (typeof HTMLIFrameElement === 'undefined' || !(frame instanceof HTMLIFrameElement)) return;

      frame.__newDebugBarMailPreviewObserver?.disconnect?.();

      try {
        const frameDocument = frame.contentDocument;
        const body = frameDocument?.body;
        const root = frameDocument?.documentElement;

        if (body && root) {
          let scheduled = false;
          const resize = () => {
            scheduled = false;
            if (!this.mailPreviewActive || frame.isConnected === false) return;
            const height = Math.min(
              100_000,
              Math.max(320, body.scrollHeight, body.offsetHeight, root.scrollHeight, root.offsetHeight),
            );

            const currentHeight = Number.parseFloat(frame.style.height);
            if (!Number.isFinite(currentHeight) || Math.abs(currentHeight - height) > 1) {
              frame.style.setProperty('height', `${Math.ceil(height)}px`, 'important');
            }
            this.layoutMailPreviewFrame(frame);
          };
          const scheduleResize = () => {
            if (scheduled) return;
            scheduled = true;
            browser.nextFrame(resize);
          };

          if (typeof ResizeObserver === 'function') {
            const observer = new ResizeObserver(scheduleResize);
            observer.observe(body);
            frame.__newDebugBarMailPreviewObserver = observer;
          }

          scheduleResize();
        }
      } catch {
        // Sandboxed HTML previews report their own height below.
      }

      this.layoutMailPreviewFrame(frame);
      frame.contentWindow?.postMessage({ type: 'newdebugbar:measure-mail-preview' }, '*');
    },

    mailPreviewUrl(message = this.selectedMailMessage) {
      if (!message) return null;

      return this.mailPreviewFormat === 'text' ? message.text_url : message.html_url;
    },
  };
}
