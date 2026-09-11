/** Keeps one phase explanation open across hover, keyboard focus, and touch. */
export function createTraceHelp() {
  let closeTimer;

  return {
    phaseHelpIndex: null,
    phaseHelpPinned: false,
    phaseHelpTrigger: null,

    holdPhaseHelp() {
      clearTimeout(closeTimer);
    },

    showPhaseHelp(index, trigger) {
      this.holdPhaseHelp();
      if (this.phaseHelpIndex !== index) this.phaseHelpPinned = false;
      this.phaseHelpIndex = index;
      this.phaseHelpTrigger = trigger;
    },

    togglePhaseHelp(index, trigger) {
      if (this.phaseHelpIndex === index && this.phaseHelpPinned) {
        this.closePhaseHelp();
      } else {
        this.showPhaseHelp(index, trigger);
        this.phaseHelpPinned = true;
      }
    },

    leavePhaseHelp() {
      this.holdPhaseHelp();
      if (this.phaseHelpPinned || this.phaseHelpTrigger?.matches(':focus-visible')) return;
      closeTimer = setTimeout(() => this.closePhaseHelp(), 150);
    },

    closePhaseHelp() {
      this.holdPhaseHelp();
      this.phaseHelpIndex = null;
      this.phaseHelpPinned = false;
      this.phaseHelpTrigger = null;
    },

    destroy() {
      this.holdPhaseHelp();
    },
  };
}
