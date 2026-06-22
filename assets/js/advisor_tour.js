/* مَدار · Advisor Panel Interactive Tutorial (پکیج آموزش تعاملی پنل مشاور) */
(() => {
  'use strict';

  /* ===== Spotlight Overlay ===== */
  let overlayEl = null;
  let overlayHole = null;

  function createOverlay() {
    overlayEl = document.createElement('div');
    overlayEl.id = 'tour-overlay';
    overlayEl.style.cssText = `
      position: fixed; inset: 0; z-index: 100000;
      background: rgba(6,11,9,0.82);
      backdrop-filter: blur(3px);
      -webkit-backdrop-filter: blur(3px);
      transition: opacity 0.25s ease;
      pointer-events: none;
    `;
    document.body.appendChild(overlayEl);
  }

  function removeOverlay() {
    if (overlayEl) { overlayEl.remove(); overlayEl = null; }
  }

  function positionHole(targetEl) {
    if (!overlayEl || !targetEl) return;
    // Remove old hole
    if (overlayHole) { overlayHole.remove(); overlayHole = null; }

    const r = targetEl.getBoundingClientRect();
    const pad = 8;
    const x = Math.max(0, r.left - pad);
    const y = Math.max(0, r.top - pad);
    const w = r.width + pad * 2;
    const h = r.height + pad * 2;

    // Use clip-path to cut a hole
    overlayEl.style.clipPath = `polygon(
      0% 0%, 100% 0%, 100% 100%, 0% 100%, 0% 0%,
      ${x}px ${y}px,
      ${x + w}px ${y}px,
      ${x + w}px ${y + h}px,
      ${x}px ${y + h}px,
      ${x}px ${y}px
    )`;
    overlayEl.style.pointerEvents = 'none';

    // Make the target area interactive
    const hole = document.createElement('div');
    hole.style.cssText = `
      position: fixed;
      left: ${x}px; top: ${y}px;
      width: ${w}px; height: ${h}px;
      z-index: 100005;
      pointer-events: auto;
      border: 2px solid var(--gold, #b2945f);
      border-radius: 12px;
      box-shadow: 0 0 0 4px rgba(178,148,95,0.3), 0 0 20px rgba(178,148,95,0.15);
    `;
    hole.addEventListener('click', (e) => {
      e.stopPropagation();
    }, true);
    document.body.appendChild(hole);
    overlayHole = hole;
  }

  function removeHole() {
    if (overlayHole) { overlayHole.remove(); overlayHole = null; }
  }

  /* ===== Styles ===== */
  const style = document.createElement('style');
  style.textContent = `
    .tour-tooltip {
      position: fixed;
      z-index: 100010;
      width: min(340px, calc(100vw - 24px));
      background: linear-gradient(160deg, #15201b, #0c1512);
      border: 2px solid var(--gold, #b2945f);
      border-radius: 18px;
      box-shadow: 0 15px 45px rgba(0,0,0,0.7);
      padding: 18px;
      direction: rtl;
      color: var(--text-1, #e0e0e0);
      font-family: inherit;
      animation: tourPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes tourPop {
      from { opacity: 0; transform: scale(0.92) translateY(10px); }
      to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .tour-tooltip h4 {
      font-size: 0.98rem;
      font-weight: 1000;
      color: var(--gold-light, #d4c496);
      margin: 0 0 8px 0;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .tour-tooltip p {
      font-size: 0.82rem;
      line-height: 1.6;
      color: var(--text-2, #b0b0b0);
      margin: 0 0 14px 0;
    }
    .tour-steps-indicator {
      font-size: 0.72rem;
      font-weight: 900;
      color: var(--text-3, #777);
    }
    .tour-btn-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
    }
    .tour-btn {
      border: 1px solid rgba(255,255,255,0.1);
      background: rgba(255,255,255,0.03);
      color: var(--text-1, #e0e0e0);
      border-radius: 10px;
      padding: 6px 12px;
      font-size: 0.75rem;
      font-weight: 900;
      cursor: pointer;
      transition: all 0.2s;
    }
    .tour-btn:hover {
      background: rgba(255,255,255,0.08);
      border-color: rgba(255,255,255,0.2);
    }
    .tour-btn.next {
      background: linear-gradient(135deg, var(--gold-light, #d4c496), var(--gold, #b2945f));
      color: #111;
      border: none;
    }
    .tour-btn.next:hover {
      box-shadow: 0 0 10px rgba(178,148,95,0.3);
    }
    .tour-test-box {
      background: rgba(178,148,95,0.12);
      border: 1px dashed var(--gold, #b2945f);
      border-radius: 10px;
      padding: 10px;
      margin-bottom: 12px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .tour-test-task {
      background: #1c2823;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 8px;
      padding: 6px 10px;
      font-size: 0.75rem;
      font-weight: 900;
      color: var(--text-1, #e0e0e0);
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .tour-test-task:hover {
      border-color: var(--gold-light, #d4c496);
      background: #203029;
    }
    .tour-test-task.success {
      border-color: var(--success, #5fae7b) !important;
      background: rgba(95,174,123,0.1) !important;
      color: var(--success, #5fae7b) !important;
    }
    .tour-success-popup {
      position: fixed; inset: 0;
      z-index: 100020;
      display: flex; align-items: center; justify-content: center;
      pointer-events: none;
    }
    .tour-success-popup .popup-box {
      background: rgba(95,174,123,0.92);
      color: white;
      padding: 18px 32px;
      border-radius: 18px;
      font-weight: bold;
      font-size: 1.1rem;
      box-shadow: 0 10px 40px rgba(0,0,0,0.5);
      transform: scale(0.9);
      opacity: 0;
      transition: all 0.3s;
    }
    .tour-success-popup .popup-box.show {
      transform: scale(1);
      opacity: 1;
    }
  `;
  document.head.appendChild(style);

  /* ===== Tour Logic ===== */
  let currentStep = 0;
  let tooltipEl = null;
  let testCompleted = false;

  const steps = [
    {
      target: '#sidebar',
      title: 'سایدبار ناوبری مَدار',
      text: 'این بخش سایدبار هوشمند مَدار است. از اینجا به تمام کارهای مدیریتی خود دسترسی دارید: تعریف برنامه‌ها، هماهنگی جلسات، بررسی کارنامه‌ها و ارتباط با دانش‌آموزان.'
    },
    {
      target: '.tb-actions',
      title: 'پیام‌ها و اعلان‌های فوری',
      text: 'در این گوشه، پیام‌های جدیدِ ارسالی دانش‌آموزان و هشدارهای مهم سیستم (مانند گزارش‌های روزانه‌ی قفل‌شده به دلیل اتمام مهلت شبانه) را به صورت زنده رصد و مدیریت می‌کنید.'
    },
    {
      target: '.stat-cards',
      title: 'آمار کلان و پیشرفت زنده',
      text: 'این بخش خلاصه عملکرد کل دانش‌آموزان تحت نظارت شما را نمایش می‌دهد. نرخ تکمیل تسک‌ها و تعداد دانش‌آموزان در انتظار تأیید را از همین‌جا فوراً دنبال کنید.'
    },
    {
      target: '#meetingForm',
      fallback: '.content',
      title: 'تست تعاملی سیستم هوشمند ⚡',
      text: 'بیایید یک آزمون کوچک انجام دهیم! این کادر شبیه‌ساز برنامه‌ریزی هوشمند مَدار است. برای تست تعاملی، لطفاً روی پارت درسی زیر کلیک کنید تا ذخیره‌سازی خودکار و یادگیری سیستم را امتحان کنید:',
      isTest: true
    }
  ];

  function findTarget(step) {
    let el = document.querySelector(step.target);
    if (!el && step.fallback) {
      el = document.querySelector(step.fallback);
    }
    return el;
  }

  function showStep(index) {
    // Cleanup previous
    cleanupStep();

    if (index < 0 || index >= steps.length) {
      endTour();
      return;
    }

    currentStep = index;
    const step = steps[index];
    const target = findTarget(step);

    if (!target) {
      // Skip to next if target not found
      showStep(index + 1);
      return;
    }

    // Create overlay with hole
    createOverlay();
    positionHole(target);

    // Scroll target into view
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Create tooltip after a brief delay for scroll
    setTimeout(() => {
      if (currentStep !== index) return; // Step changed during scroll
      tooltipEl = document.createElement('div');
      tooltipEl.className = 'tour-tooltip';

      let innerHTML = `
        <h4>🎓 ${step.title}</h4>
        <p>${step.text}</p>
      `;

      if (step.isTest) {
        innerHTML += `
          <div class="tour-test-box" id="tourTestBox">
            <div class="tour-test-task" id="tourTestTask">
              <span>📚 مطالعه زیست‌شناسی دوازدهم (فصل ۳)</span>
              <span style="color: var(--gold, #b2945f); font-size: 10px;">کلیک کنید ⚡</span>
            </div>
          </div>
        `;
      }

      innerHTML += `
        <div class="tour-btn-row">
          <span class="tour-steps-indicator">مرحله ${fa(index + 1)} از ${fa(steps.length)}</span>
          <div style="display:flex;gap:8px">
            ${index > 0 ? `<button type="button" class="tour-btn" id="tourPrevBtn">قبلی</button>` : ''}
            <button type="button" class="tour-btn ${index === steps.length - 1 ? '' : 'next'}" id="tourNextBtn">
              ${index === steps.length - 1 ? 'پایان آموزش' : 'بعدی'}
            </button>
          </div>
        </div>
      `;

      tooltipEl.innerHTML = innerHTML;
      document.body.appendChild(tooltipEl);

      // Position tooltip
      const tr = target.getBoundingClientRect();
      const tw = tooltipEl.offsetWidth;
      const th = tooltipEl.offsetHeight;
      const vw = window.innerWidth;
      const vh = window.innerHeight;

      // Try below the target
      let top = tr.bottom + 16;
      let left = tr.left;

      // If not enough space below, try above
      if (top + th > vh - 10) {
        top = tr.top - th - 16;
        if (top < 10) top = 10;
      }

      // RTL: try to keep tooltip on the right side
      left = Math.min(left, vw - tw - 10);
      left = Math.max(10, left);

      tooltipEl.style.top = top + 'px';
      tooltipEl.style.left = left + 'px';

      // Ensure tooltip is fully visible
      const ttRect = tooltipEl.getBoundingClientRect();
      if (ttRect.bottom > vh) {
        tooltipEl.style.top = Math.max(10, vh - ttRect.height - 10) + 'px';
      }
      if (ttRect.right > vw) {
        tooltipEl.style.left = Math.max(10, vw - ttRect.width - 10) + 'px';
      }

      // Bind events
      const nextBtn = document.getElementById('tourNextBtn');
      if (nextBtn) {
        nextBtn.onclick = () => {
          if (step.isTest && !testCompleted) {
            return; // Don't advance until test is done
          }
          showStep(index + 1);
        };
      }

      const prevBtn = document.getElementById('tourPrevBtn');
      if (prevBtn) {
        prevBtn.onclick = () => showStep(index - 1);
      }

      if (step.isTest) {
        const task = document.getElementById('tourTestTask');
        if (task) {
          task.onclick = () => {
            if (testCompleted) return;
            testCompleted = true;
            task.className = 'tour-test-task success';
            task.innerHTML = '<span>✓ ذخیره‌سازی و آنالیز هوشمند انجام شد!</span>';

            // Success popup
            const popup = document.createElement('div');
            popup.className = 'tour-success-popup';
            popup.innerHTML = '<div class="popup-box" id="tourSuccessBox">🎓 آفرین! یادگیری هوشمند مَدار منبع کتاب و ساعت پیشنهادی این درس را یاد گرفت!</div>';
            document.body.appendChild(popup);
            requestAnimationFrame(() => {
              const box = document.getElementById('tourSuccessBox');
              if (box) box.classList.add('show');
            });

            setTimeout(() => {
              popup.remove();
              showStep(index + 1);
            }, 2200);
          };
        }
      }
    }, 300);
  }

  function cleanupStep() {
    removeHole();
    removeOverlay();
    if (tooltipEl) { tooltipEl.remove(); tooltipEl = null; }
  }

  function endTour() {
    cleanupStep();
    testCompleted = false;
    // Show a nice completion message
    const msg = document.createElement('div');
    msg.style.cssText = `
      position: fixed; inset: 0; z-index: 100050;
      display: flex; align-items: center; justify-content: center;
      background: rgba(6,11,9,0.85);
      backdrop-filter: blur(5px);
      -webkit-backdrop-filter: blur(5px);
      animation: fadeIn 0.3s ease;
    `;
    msg.innerHTML = `
      <div style="
        background: linear-gradient(160deg, #15201b, #0c1512);
        border: 2px solid var(--gold, #b2945f);
        border-radius: 22px;
        padding: 40px;
        text-align: center;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.6);
        animation: tourPop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      ">
        <div style="font-size: 3rem; margin-bottom: 16px;">🎓✨</div>
        <h3 style="color: var(--gold-light, #d4c496); font-size: 1.3rem; margin-bottom: 12px;">تبریک!</h3>
        <p style="color: var(--text-2, #b0b0b0); font-size: 0.9rem; line-height: 1.7; margin-bottom: 24px;">
          آموزش تعاملی پنل مشاور مَدار با موفقیت به پایان رسید. حالا شما یک مشاور حرفه‌ای مَدار هستید!
        </p>
        <button class="tour-btn next" onclick="this.parentElement.parentElement.remove()" style="
          padding: 12px 32px;
          font-size: 1rem;
          font-weight: 900;
          cursor: pointer;
        ">بستن</button>
      </div>
    `;
    document.body.appendChild(msg);
  }

  function fa(n) {
    const FA = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return String(n).replace(/\d/g, d => FA[d]);
  }

  // Start tour on button click
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('advisorTourBtn');
    if (btn) {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        testCompleted = false;
        showStep(0);
      });
    }
  });

  // Escape key closes tour
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (tooltipEl || overlayEl) {
        cleanupStep();
      }
    }
  });

})();
