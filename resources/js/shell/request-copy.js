/** Owns the short-lived feedback for copying the current request URL. */
export function createRequestCopyFeedback(requestUrl, copyText, browser) {
  let timer = null;
  let version = 0;

  return {
    copyFeedback: '',

    async copyRequestUrl() {
      const current = ++version;
      browser.cancelSchedule(timer);
      timer = null;
      this.copyFeedback = '';
      const copied = await copyText(requestUrl);
      if (current !== version) return;

      this.copyFeedback = copied ? 'Copied' : 'Copy failed';
      timer = browser.schedule(() => {
        this.copyFeedback = '';
        timer = null;
      }, 3000);
    },

    destroy() {
      version++;
      browser.cancelSchedule(timer);
    },
  };
}
