document.addEventListener('DOMContentLoaded', () => {
  const safeQuery = (sel, root = document) => {
    try { return root.querySelector(sel); } catch { return null; }
  };
  const safeQueryAll = (sel, root = document) => {
    try { return Array.from(root.querySelectorAll(sel)); } catch { return []; }
  };

  const genderButtons = safeQueryAll('#genderInput button');
  const genderValue = safeQuery('#genderValue');
  if (genderButtons.length && genderValue) {
    genderButtons.forEach(btn => {
      if (btn.dataset.value === (genderValue.value || '')) btn.classList.add('active');
      btn.addEventListener('click', () => {
        genderButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        genderValue.value = btn.dataset.value;
      });
    });
  }

  function toggleVisibility(buttonSelector, inputSelector) {
    safeQueryAll(buttonSelector).forEach(btn => {
      btn.addEventListener('click', () => {
        const input = safeQuery(inputSelector);
        if (!input) return;
        input.type = (input.type === 'password') ? 'text' : 'password';
        const icon = btn.querySelector('i');
        if (icon) icon.classList.toggle('fa-eye-slash');
      });
    });
  }

  toggleVisibility('.toggle-new-password', '#passwordInput');
  toggleVisibility('.toggle-new-password2', '#passwordConfirm');

  const editBtn = safeQuery('#editBtn');
  const saveCancel = safeQuery('#saveCancelBtns');
  const form = safeQuery('#profileForm');

  const pwRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{7,}$/;

  if (form) {
    form.addEventListener('submit', (e) => {
      const newPw = safeQuery('#passwordInput')?.value || '';
      const confirmPw = safeQuery('#passwordConfirm')?.value || '';

      if (newPw.trim() === '') {
        return;
      }

      if (!pwRegex.test(newPw)) {
        e.preventDefault();
        alert('New password must be >6 chars and include uppercase, lowercase, digit and special character.');
        return;
      }

      if (newPw !== confirmPw) {
        e.preventDefault();
        alert('New password and confirm password do not match.');
        return;
      }

    });
  }

  function clearAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(a => {
      a.style.transition = 'opacity 220ms ease';
      a.style.opacity = '0';
      setTimeout(() => a.remove(), 240);
    });
  }

  function toggleEdit(on) {
    function togglePair(displayId, inputId) {
      const disp = safeQuery(displayId);
      const inp  = safeQuery(inputId);
      if (disp) disp.classList.toggle('d-none', on);
      if (inp) inp.classList.toggle('d-none', !on);
    }

    if (on) clearAlerts();

    togglePair('#usernameDisplay', '#usernameInput');
    togglePair('#emailDisplay', '#emailInput');
    togglePair('#genderDisplay', '#genderInput');
    togglePair('#dobDisplay', '#dobInput');

    const pwdDisplay = safeQuery('#passwordDisplay');
    const pwdGroup = safeQuery('.password-group');
    if (pwdDisplay) pwdDisplay.classList.toggle('d-none', on);
    if (pwdGroup) pwdGroup.classList.toggle('d-none', !on);

    if (editBtn) editBtn.classList.toggle('d-none', on);
    if (saveCancel) saveCancel.classList.toggle('d-none', !on);
  }

  if (editBtn) {
    editBtn.addEventListener('click', () => toggleEdit(true));
  }

  window.cancelEdit = () => {
    if (form) form.classList.remove('was-validated');
    toggleEdit(false);

    const np = safeQuery('#passwordInput');
    const nc = safeQuery('#passwordConfirm');
    if (np) np.value = '';
    if (nc) nc.value = '';
  };

  safeQueryAll('.field-edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      safeQueryAll('#profileForm input, #profileForm textarea, #profileForm select').forEach(i => i.disabled = true);
      const targetSel = btn.dataset.target;
      if (!targetSel) return;
      const target = safeQuery(targetSel);
      if (!target) return;
      target.disabled = false;
      if (saveCancel) saveCancel.classList.remove('d-none');
      if (editBtn) editBtn.classList.add('d-none');
    });
  });

  window.addEventListener('error', event => {
    console.error('Unhandled JS error:', event.error || event.message, event.filename, event.lineno);
  });
});
