/** Owns clipboard feedback and its lifetime for every copy control. */
export function createCopyControl(copyText, browser) {
  let timer = null;
  let version = 0;
  let currentValue;

  return {
    copyStatus: '',

    resetCopyFeedback() {
      version++;
      browser.cancelSchedule(timer);
      timer = null;
      this.copyStatus = '';
    },

    syncCopyValue(value) {
      if (value === currentValue) return;
      currentValue = value;
      this.resetCopyFeedback();
    },

    async copyWithFeedback(value) {
      this.resetCopyFeedback();
      const current = version;
      const copied = await copyText(value);
      if (current !== version) return;

      this.copyStatus = copied ? 'Copied' : 'Copy failed';
      timer = browser.schedule(() => {
        this.copyStatus = '';
        timer = null;
      }, 3000);
    },

    destroy() {
      this.resetCopyFeedback();
    },
  };
}
