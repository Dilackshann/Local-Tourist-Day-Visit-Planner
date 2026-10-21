document.querySelectorAll(".footer-contact-form").forEach((form) => {
  const button = form.querySelector('button[type="submit"]');
  const status = form.querySelector(".contact-status");

  if (!button || !status) return;

  let sending = false;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (sending || !form.reportValidity()) return;

    sending = true;
    button.disabled = true;
    button.textContent = "Sending...";
    status.textContent = "";

    try {
      const response = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        headers: {
          Accept: "application/json"
        }
      });

      let result;

      try {
        result = await response.json();
      } catch {
        throw new Error(
          "Unexpected server response. Please contact the site administrator."
        );
      }

      if (!response.ok || result.success !== true) {
        throw new Error(result.message || "Unable to send your message.");
      }

      status.textContent = result.message;
      form.reset();
    } catch (error) {
      status.textContent =
        error instanceof TypeError
          ? "Connection failed. Please check your connection and try again."
          : error.message;
    } finally {
      sending = false;
      button.disabled = false;
      button.textContent = "Send Message";
    }
  });
});