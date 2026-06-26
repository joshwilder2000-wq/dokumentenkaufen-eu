// Cookie UI disabled by request.
(function() {
  'use strict';

  function emptyPreferences() {
    return { necessary: true, analytics: false, marketing: false };
  }

  window.CookieConsent = {
    get: emptyPreferences,
    has: function() { return true; },
    show: function() {},
    acceptAll: function() {}
  };
})();
