// AUTO-REFRESH SESSION MANAGER - Updated to 30 minutes
class SessionManager {
  constructor() {
    this.refreshInterval = 30 * 60 * 1000; // 30 minutes
    this.sessionLifetime =
      parseInt(
        document.querySelector('meta[name="session-lifetime"]')?.content ||
          "28800"
      ) * 1000;

    this.init();
  }

  init() {
    // Auto-refresh session every 30 minutes
    setInterval(() => this.refreshSession(), this.refreshInterval);

    // Refresh on user activity
    this.bindActivityEvents();

    console.log("Session Manager initialized - auto-refresh every 30 minutes");
  }

  async refreshSession() {
    try {
      const response = await fetch("/pg-management/api/refresh-session.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      const data = await response.json();

      if (data.success) {
        console.log("Session refreshed successfully");
      } else {
        console.warn("Session refresh failed:", data.message);
        this.handleSessionExpiry();
      }
    } catch (error) {
      console.warn("Session refresh error:", error);
    }
  }

  bindActivityEvents() {
    const events = [
      "mousedown",
      "mousemove",
      "keypress",
      "scroll",
      "touchstart",
      "click",
    ];
    let lastActivity = Date.now();

    events.forEach((event) => {
      document.addEventListener(
        event,
        () => {
          const now = Date.now();
          // Refresh session if 5+ minutes since last activity
          if (now - lastActivity > 5 * 60 * 1000) {
            this.refreshSession();
            lastActivity = now;
          }
        },
        true
      );
    });
  }

  handleSessionExpiry() {
    alert(
      "Your session has expired. You will be redirected to the login page."
    );
    window.location.href = "/pg-management/index.php?error=session_expired";
  }
}

//  AUTO-START SESSION MANAGER
document.addEventListener("DOMContentLoaded", function () {
  window.sessionManager = new SessionManager();
});