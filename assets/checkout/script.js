/**
 * Adaptado de contact-form/script.js
 * - Ajuste: carrega config via CFTheme.configUrl
 */
let debugLogs = true;
const log = (...args) => { if (debugLogs) console.log(...args); };
const warn = (...args) => { if (debugLogs) console.warn(...args); };

let isInitialLoad = true;

document.addEventListener('DOMContentLoaded', async () => {
  let config = {};
  try {
    const configUrl = (window.CFTheme && CFTheme.configUrl) ? CFTheme.configUrl : 'config.json';
    const response = await fetch(configUrl);
    const rawConfig = await response.json();
    const activeEnv = rawConfig.environment || 'production';
    config = rawConfig.environments[activeEnv] || {};
    debugLogs = config.enableDebugLogs === true;
    log('Configuration loaded:', config);
  } catch (error) {
    console.warn('Could not load configuration, using defaults:', error);
    config = { enableDebugLogs: false, useDefaultFormValues: false, formDefaultValues: {} };
  }

  const form = document.getElementById('eulen-form');
  const steps = Array.from(form.querySelectorAll('.form-step'));
  const nextButtons = form.querySelectorAll('.next-btn');
  const prevButtons = form.querySelectorAll('.prev-btn');
  const submitButtons = form.querySelectorAll('.submit-btn');
  const formContainer = document.querySelector('.form-container');
  const progressBar = formContainer?.querySelector('.progress-bar .progress');
  const finalMessageContentContainer = document.getElementById('final-message-content');

  let currentStep = 0;
  const formData = {};
  let selectedCategory = '';

  const DEFAULT_STATE = { asset:'', category:'', amount_brl:'', amount_out:'', network:'', wallet:'', profileType:'' };
  let state = loadState();

  function loadState(){
    try {
      const s = sessionStorage.getItem('eulenState');
      if (s) return { ...DEFAULT_STATE, ...JSON.parse(s) };
    } catch(_){ }
    const get = (n) => form.querySelector(`input[name="state_${n}"]`)?.value || '';
    return {
      asset: get('asset') || '',
      category: get('category'),
      amount_brl: get('amount_brl'),
      amount_out: get('amount_out'),
      network: get('network'),
      wallet: get('wallet'),
      profileType: get('profileType')
    };
  }
  function writeHidden(){
    for (const [k,v] of Object.entries(state)) {
      const el = form.querySelector(`input[name="state_${k}"]`);
      if (el) el.value = v ?? '';
    }
    try { sessionStorage.setItem('eulenState', JSON.stringify(state)); } catch(_){ }
  }
  function setState(patch){
    state = { ...state, ...patch };
    writeHidden();
    renderFromState();
  }
  function renderFromState(){
    document.documentElement.classList.toggle('asset-btc', state.asset === 'btc');
    document.documentElement.classList.toggle('asset-depix', state.asset === 'depix');

    const btcRadio = document.getElementById('category-onboarding');
    const dpxRadio = document.getElementById('category-security');
    if (btcRadio) btcRadio.checked = (state.asset === 'btc');
    if (dpxRadio) dpxRadio.checked = (state.asset === 'depix');

    const netLiq = document.getElementById('network-liquid');
    const netLtn = document.getElementById('network-lightning');
    if (netLiq) netLiq.checked = (state.network === 'liquid');
    if (netLtn) netLtn.checked = (state.network === 'lightning');

    const walletInput = document.getElementById('wallet-address');
    const walletHint = document.getElementById('wallet-hint');
    if (walletInput) walletInput.value = state.wallet || '';
    if (walletInput && walletHint){
      if (state.network === 'liquid') {
        walletInput.placeholder = 'lq1… ou VJL…';
        walletHint.textContent = 'Liquid: lq1… (confidencial/blech32) ou CT… (Base58/confidencial).';
      } else {
        walletInput.placeholder = 'bc1…';
        walletHint.textContent = '';
      }
    }

    const chipAsset = document.querySelector('#chips #chip-asset');
    const chipAmt = document.querySelector('#chips #chip-amount');
    const chipNet = document.querySelector('#chips #chip-network');
    if (chipAsset) chipAsset.textContent = state.asset === 'depix' ? 'DePix' : 'Bitcoin';
    if (chipAmt) chipAmt.textContent = state.amount_out ? `${state.amount_out} ${state.asset==='depix'?'DPX':'BTC'}` : '';
    if (chipNet) chipNet.textContent = state.network ? (state.network==='liquid'?'Liquid':'Lightning') : '';
  }

  window.EulenState = {
    get: () => ({ ...state }),
    set: (patch) => setState(patch),
    render: () => renderFromState()
  };

  // Centraliza o template visual do botão "Próximo" em um único lugar
  function createNextButtonTemplate(button) {
    if (!button) return;
    // Preserva classes existentes e adiciona classes de navegação comuns
    button.classList.add('nav-btn', 'nav-btn--next');
    // Acessibilidade
    button.setAttribute('aria-label', 'Próximo');
    button.setAttribute('title', 'Próximo');
    // Limpa qualquer conteúdo anterior (texto "Próximo") e injeta o SVG de seta para a direita
    button.innerHTML = '';
    const svgNS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    const path = document.createElementNS(svgNS, 'path');
    // Seta para a direita (espelha a forma do botão Voltar)
    path.setAttribute('d', 'M8.5 5l7 7-7 7');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    svg.appendChild(path);
    button.appendChild(svg);
  }

  // Aplica o template a todos os botões Próximo existentes no DOM
  nextButtons.forEach((btn) => createNextButtonTemplate(btn));

  // Helper para estado de carregamento sem alterar conteúdo do botão (apenas acessibilidade/estilo)
  function setNextButtonLoadingState(button, isLoading) {
    if (!button) return;
    button.classList.toggle('is-loading', !!isLoading);
    button.toggleAttribute('aria-busy', !!isLoading);
    // Mantém o ícone; apenas ajusta label/title para leitores de tela
    if (isLoading) {
      button.setAttribute('aria-label', 'Criando...');
      button.setAttribute('title', 'Criando...');
    } else {
      button.setAttribute('aria-label', 'Próximo');
      button.setAttribute('title', 'Próximo');
    }
  }

  document.getElementById('network-liquid')?.addEventListener('change', (e) => {
    if (e.target.checked) {
      setState({ network:'liquid' });
      setTimeout(() => {
        const from = document.querySelector('.form-step[data-step="2.6"] .step-state-chips');
        const to = document.querySelector('.form-step[data-step="2.7"] .step-state-chips');
        if (from && to) to.innerHTML = from.innerHTML;
        const stepsEls = Array.from(document.querySelectorAll('.form-step'));
        const nextIdx = stepsEls.findIndex(s => s.dataset.step === '2.7');
        if (nextIdx !== -1) showStep(nextIdx);
      }, 0);
    }
  });
  document.getElementById('network-lightning')?.addEventListener('change', (e) => {
    if (e.target.checked) {
      setState({ network:'lightning' });
      setTimeout(() => {
        const from = document.querySelector('.form-step[data-step="2.6"] .step-state-chips');
        const to = document.querySelector('.form-step[data-step="2.7"] .step-state-chips');
        if (from && to) to.innerHTML = from.innerHTML;
        const stepsEls = Array.from(document.querySelectorAll('.form-step'));
        const nextIdx = stepsEls.findIndex(s => s.dataset.step === '2.7');
        if (nextIdx !== -1) showStep(nextIdx);
      }, 0);
    }
  });
  document.getElementById('wallet-address')?.addEventListener('input', (e) => {
    const v = (e.target.value || '').trim();
    setState({ wallet: v });
    // validação básica cliente: CT... (Base58) ou lq1... (blech32)
    const isCT = /^CT[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{48,}$/.test(v);
    const islq = /^lq1[023456789ac-hj-np-z]{20,}$/.test(v.toLowerCase());
    const err = document.getElementById('walletAddress-error');
    if (v && !(isCT || islq)) { if (err) { err.textContent = 'Endereço Liquid inválido.'; err.classList.add('visible'); } e.target.classList.add('invalid'); }
    else { if (err) { err.classList.remove('visible'); } e.target.classList.remove('invalid'); }
  });

  // Conversão BRL -> BTC (CoinGecko) e exibição de taxas, idêntico ao tema
  (function attachBrlConversion(){
    const amountInput = document.getElementById('desiredAmountBRL');
    const outBtc = document.getElementById('convertedAmountBTC');
    const amountInputDPX = document.getElementById('desiredAmountBRL_DPX');
    const outDPX = document.getElementById('convertedAmountDPX');
    function getFeesNote(){ return document.querySelector('.form-step.active .amount-fees') || document.querySelector('.amount-fees'); }
    if (!amountInput || !outBtc) return;

    let lastFetchAt = 0; let cachedBtcBrl = null; let debounce;
    function saveCacheLS(price){ try { localStorage.setItem('btcBrlPrice', String(price)); localStorage.setItem('btcBrlAt', String(Date.now())); } catch(_) {} }
    function loadCacheLS(maxAgeMs){ try { const at = Number(localStorage.getItem('btcBrlAt') || 0); const price = Number(localStorage.getItem('btcBrlPrice') || 0); if (price>0 && Date.now()-at<maxAgeMs) return price; } catch(_) {} return null; }
    async function getBtcPriceBRL(){
      const now = Date.now();
      if (cachedBtcBrl && now - lastFetchAt < 60000) return cachedBtcBrl;
      const ls = loadCacheLS(5*60*1000); if (ls) { cachedBtcBrl=ls; lastFetchAt=now; return cachedBtcBrl; }
      const endpoint='https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=brl';
      const fetchWithTimeout=(ms)=> new Promise((resolve,reject)=>{ const ctrl=new AbortController(); const t=setTimeout(()=>{ctrl.abort();reject(new Error('timeout'));},ms); fetch(endpoint,{signal:ctrl.signal}).then(r=>{clearTimeout(t);resolve(r);}).catch(err=>{clearTimeout(t);reject(err);}); });
      const attempts=[1200,2000,3000];
      for (const ms of attempts){ try{ const r=await fetchWithTimeout(ms); if(!r.ok) continue; const j=await r.json(); if(j?.bitcoin?.brl){ cachedBtcBrl=j.bitcoin.brl; lastFetchAt=now; saveCacheLS(cachedBtcBrl); return cachedBtcBrl; } }catch(_){} }
      if (cachedBtcBrl) return cachedBtcBrl; const any=loadCacheLS(365*24*60*60*1000); if(any){ cachedBtcBrl=any; lastFetchAt=now; return cachedBtcBrl; } return null;
    }
    function toNumberBRL(str){ if(!str) return 0; const cleaned=String(str).replace(/[^0-9]/g,''); const v=parseInt(cleaned,10); return isNaN(v)?0:v; }

    async function updateBRLConversion(){
      const brlRaw = toNumberBRL(amountInput.value);
      const afterFixed = Math.max(0, brlRaw - 1);
      const brlNet = afterFixed * 0.95; // -5%
      if (document.documentElement.classList.contains('asset-btc')){
        const btcRow = document.querySelector('.amount-stack .amount-row:last-of-type');
        btcRow?.classList.add('loading');
        const price = await getBtcPriceBRL();
        btcRow?.classList.remove('loading');
        const spread = (typeof config?.spreadBtc === 'number') ? Number(config.spreadBtc) : 0.015; // 1.5% spread
        const btcGross = price ? (brlNet / price) : 0;
        const btcNet = btcGross * (1 - spread);
        outBtc.value = price ? btcNet.toFixed(8) : '';
        const feesEl = getFeesNote();
        if (feesEl){
          if (price){ const brlFees = brlRaw - brlNet; const btcFees = brlFees / price; feesEl.innerHTML = `Taxa: ${btcFees.toFixed(8)} BTC <span class="fee-expl">(R$1 transação + 5%)</span>`; }
          else { feesEl.innerHTML = `Taxa: <span class=\"fee-expl\">(R$1 transação + 5%)</span>`; }
        }
      } else if (document.documentElement.classList.contains('asset-depix')) {
        if (typeof outDPX !== 'undefined' && outDPX) { outDPX.value = brlNet > 0 ? brlNet.toFixed(2) : ''; }
        const feesEl = getFeesNote();
        if (feesEl) { const brlFees = brlRaw - brlNet; feesEl.innerHTML = `Taxa: ${brlFees.toFixed(2)} DPX <span class=\"fee-expl\">(R$1 transação + 5%)</span>`; }
      } else { outBtc.value = ''; }
    }

    amountInput?.addEventListener('input', () => {
      const onlyDigits = amountInput.value.replace(/[^0-9]/g,'');
      amountInput.value = onlyDigits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      clearTimeout(debounce); debounce = setTimeout(updateBRLConversion, 200);
    });
    amountInputDPX?.addEventListener('input', () => {
      const onlyDigits = amountInputDPX.value.replace(/[^0-9]/g,'');
      amountInputDPX.value = onlyDigits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      clearTimeout(debounce); debounce = setTimeout(async () => {
        const brlRaw = toNumberBRL(amountInputDPX.value);
        const afterFixed = Math.max(0, brlRaw - 1);
        const brlNet = afterFixed * 0.95;
        if (outDPX) outDPX.value = brlNet > 0 ? brlNet.toFixed(2) : '';
        const feesEl = getFeesNote(); if (feesEl) { const brlFees = brlRaw - brlNet; feesEl.innerHTML = `Taxa: ${brlFees.toFixed(2)} DPX <span class=\"fee-expl\">(R$1 transação + 5%)</span>`; }
      }, 200);
    });
  })();

  const firstStepNextBtn = steps[0]?.querySelector('.next-btn');
  if (firstStepNextBtn) { firstStepNextBtn.disabled = true; firstStepNextBtn.classList.add('disabled'); }
  const categoryRadios = steps[0]?.querySelectorAll('input[name="category"]') || [];
  categoryRadios.forEach(radio => {
    radio.addEventListener('change', () => {
      if (firstStepNextBtn) { firstStepNextBtn.disabled = false; firstStepNextBtn.classList.remove('disabled'); }
      document.getElementById('category-error')?.classList.remove('visible');
      if (radio.id === 'category-onboarding' && radio.checked) setState({ asset:'btc', category:'onboarding' });
      if (radio.id === 'category-security' && radio.checked) setState({ asset:'depix', category:'security' });

      // Avança para a etapa de valor como no tema (sem alterar outras lógicas)
      try {
        // Atualiza o controle de fluxo para os botões Voltar dependerem da categoria correta
        if (radio.id === 'category-onboarding' && radio.checked) {
          selectedCategory = 'onboarding';
          formData.category = 'onboarding';
        } else if (radio.id === 'category-security' && radio.checked) {
          selectedCategory = 'security';
          formData.category = 'security';
        }

        const targetStep = (radio.id === 'category-onboarding') ? '2' : (radio.id === 'category-security' ? '2-depix' : null);
        if (targetStep) {
          const allSteps = Array.from(document.querySelectorAll('.form-step'));
          const idx = allSteps.findIndex(s => s.dataset.step === targetStep);
          if (idx !== -1) showStep(idx);
        }
      } catch(_) {}
    });
  });

  setState({ asset: state.asset ? '' : '' });

  nextButtons.forEach((button, index) => { if (index > 0) { button.disabled = true; button.classList.add('disabled'); } });
  submitButtons.forEach(button => { button.disabled = true; button.classList.add('disabled'); });

  function updateProgressBar() {
    if (!progressBar) return;
    let totalSteps = 0; let currentPosition = 0;
    const currentStepDataSet = steps[currentStep]?.dataset.step;
    const isShortFlow = ['feature','general','complex','jobs'].includes(selectedCategory);
    if (isShortFlow) {
      totalSteps = 2;
      switch (currentStepDataSet) {
        case '1': currentPosition = 0; break;
        case '2.5':
        case '2.6': currentPosition = 1; break;
        case '3':
        case '4': currentPosition = 2; break;
        default: currentPosition = 0;
      }
    } else {
      totalSteps = 4;
      switch (currentStepDataSet) {
        case '1': currentPosition = 0; break;
        case '2':
        case '2-depix': currentPosition = 1; break;
        case '2.6': currentPosition = 2; break;
        case '2.7': currentPosition = 3; break;
        case '3':
        case '4': currentPosition = 4; break;
        default: currentPosition = 0;
      }
    }
    const progressPercentage = (currentPosition / totalSteps) * 100;
    progressBar.style.width = `${progressPercentage}%`;
  }

  function showStep(stepIndex) {
    steps.forEach((step, index) => {
      const isActive = index === stepIndex;
      step.classList.toggle('active', isActive);
      const stepContent = step.querySelector('.step-content-wrapper');
      if (stepContent && isActive) { stepContent.style.animation = 'fadeIn 0.3s ease-in-out'; }
    });
    if (isInitialLoad && stepIndex === 0) { isInitialLoad = false; }
    currentStep = stepIndex;
    if (stepIndex >= 0 && stepIndex < steps.length) { updateProgressBar(); if (stepIndex > 0) updateNextButtonState(stepIndex); }
    renderFromState();

    try {
      const chipsFrom2 = document.querySelector('.form-step[data-step="2"] .step-state-chips');
      const chipsFrom2d = document.querySelector('.form-step[data-step="2-depix"] .step-state-chips');
      const chips26 = document.querySelector('.form-step[data-step="2.6"] .step-state-chips');
      const chips27 = document.querySelector('.form-step[data-step="2.7"] .step-state-chips');
      const source = chipsFrom2?.innerHTML ? chipsFrom2 : (chipsFrom2d?.innerHTML ? chipsFrom2d : null);
      if (source && chips26 && !chips26.innerHTML.trim()) chips26.innerHTML = source.innerHTML;
      if (chips26 && chips27 && !chips27.innerHTML.trim()) chips27.innerHTML = chips26.innerHTML;
      if (steps[stepIndex]?.dataset.step === '4') {
        const srcHtml = (chips27?.innerHTML && chips27.innerHTML.trim()) ? chips27.innerHTML : (chips26?.innerHTML || '');
        const chips4 = document.querySelector('.form-step[data-step="4"] .step-state-chips');
        if (chips4 && srcHtml) chips4.innerHTML = srcHtml;
      }
    } catch(_) {}

    if (stepIndex === 0) {
      try {
        const radios = steps[0].querySelectorAll('input[name="category"]');
        radios.forEach(r => { r.checked = false; });
        const btn = steps[0].querySelector('.next-btn');
        if (btn) { btn.disabled = true; btn.classList.add('disabled'); }
        setState({ asset:'', category:'', amount_brl:'', amount_out:'', network:'', wallet:'', profileType:'' });
      } catch(_) {}
    }
    try {
      const stepDS = steps[stepIndex]?.dataset.step;
      if (stepDS === '2.6') {
        const liq = document.getElementById('network-liquid');
        const ltn = document.getElementById('network-lightning');
        if (liq) liq.checked = false;
        if (ltn) ltn.checked = false;
      }
    } catch(_) {}
  }

  function validateStep(stepIndex, showErrors = true) {
    const currentStepElement = steps[stepIndex]; if (!currentStepElement) return false;
    const inputsToValidate = currentStepElement.querySelectorAll('input[required], textarea[required], select[required]');
    let isStepValid = true;
    currentStepElement.querySelectorAll('.error-message').forEach(msg => msg.classList.remove('visible'));
    currentStepElement.querySelectorAll('.invalid').forEach(el => el.classList.remove('invalid'));
    currentStepElement.querySelectorAll('.radio-group.invalid').forEach(group => group.classList.remove('invalid'));
    inputsToValidate.forEach(input => { if (!validateInput(input, true, showErrors)) isStepValid = false; });
    updateNextButtonState(stepIndex, isStepValid); return isStepValid;
  }

  function validateInput(input, checkRequired = false, showErrors = true) {
    if (!input) return true;
    let isFieldValid = true; let errorMessage = '';
    const value = input.value.trim(); const errorId = `${input.id || input.name}-error`;
    const errorElement = document.getElementById(errorId); const radioGroupName = input.name;
    const currentStepElement = input.closest('.form-step');
    input.classList.remove('invalid'); if (errorElement) errorElement.classList.remove('visible');
    if (input.type === 'radio') { input.closest('.radio-group')?.classList.remove('invalid'); }
    else if (input.type === 'checkbox') { input.closest('.checkbox-group')?.classList.remove('invalid'); }
    if (checkRequired && input.required) {
      if (input.type === 'checkbox' && !input.checked) { isFieldValid = false; errorMessage = 'Você precisa concordar com os termos.'; if (showErrors) input.closest('.checkbox-group')?.classList.add('invalid'); }
      else if (input.type === 'radio' && currentStepElement) { const group = currentStepElement.querySelectorAll(`input[name="${radioGroupName}"]`); if (!Array.from(group).some(radio => radio.checked)) { isFieldValid = false; errorMessage = 'Por favor, selecione uma opção.'; if (showErrors) input.closest('.radio-group')?.classList.add('invalid'); } }
      else if ((input.type !== 'checkbox' && input.type !== 'radio') && !value) { isFieldValid = false; errorMessage = 'Este campo é obrigatório.'; }
    }
    if (value && isFieldValid) {
      if ((input.id === 'fullName' || input.id === 'telegramUser') && value.length < 3) { isFieldValid = false; errorMessage = 'Deve ter no mínimo 3 caracteres.'; }
      else if (input.type === 'email') { if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) { isFieldValid = false; errorMessage = 'Por favor, digite um e-mail válido.'; } else if (value.length < 3) { isFieldValid = false; errorMessage = 'Deve ter no mínimo 3 caracteres.'; } }
    }
    if (!isFieldValid && showErrors) { input.classList.add('invalid'); if (errorElement) { errorElement.textContent = errorMessage; errorElement.classList.add('visible'); errorElement.style.display = 'block'; } }
    return isFieldValid;
  }

  function updateNextButtonState(stepIndex, isValid = null) {
    const currentStepElement = steps[stepIndex]; if (!currentStepElement) return;
    if (isValid === null) isValid = validateStep(stepIndex, false);
    const nextButton = currentStepElement.querySelector('.next-btn');
    const submitButton = currentStepElement.querySelector('.submit-btn');
    const buttonToUpdate = nextButton || submitButton; if (buttonToUpdate) { if (isValid) { buttonToUpdate.disabled = false; buttonToUpdate.classList.remove('disabled'); } else { buttonToUpdate.disabled = true; buttonToUpdate.classList.add('disabled'); } }
  }

  function collectStepData(stepIndex) {
    const currentStepElement = steps[stepIndex]; if (!currentStepElement) return;
    const inputs = currentStepElement.querySelectorAll('input, textarea, select');
    inputs.forEach(input => { const name = input.name; if (!name) return; if (input.type === 'radio') { if (input.checked) { formData[name] = input.value; if (name === 'category') selectedCategory = input.value; } } else if (input.type === 'checkbox') { formData[name] = input.checked; } else { formData[name] = input.value.trim(); } });
    if (formData.category === 'onboarding') setState({ asset:'btc', category:'onboarding' });
    if (formData.category === 'security') setState({ asset:'depix', category:'security' });
    if (formData.networkChoice) setState({ network: formData.networkChoice });
    if (formData.walletAddress) setState({ wallet: formData.walletAddress });
  }

  function determineNextStep(currentStepIndex) {
    collectStepData(currentStepIndex);
    const currentStepDataSet = steps[currentStepIndex]?.dataset.step;
    switch (currentStepDataSet) {
      case '1':
        switch (selectedCategory) {
          case 'onboarding': return steps.findIndex(step => step.dataset.step === '2');
          case 'security': return steps.findIndex(step => step.dataset.step === '2-depix');
          case 'general': return steps.findIndex(step => step.dataset.step === '3' && step.dataset.category === 'general');
          case 'feature': return steps.findIndex(step => step.dataset.step === '3' && step.dataset.category === 'feature');
          case 'jobs': return steps.findIndex(step => step.dataset.step === '3' && step.dataset.category === 'jobs');
          case 'docs': return steps.findIndex(step => step.dataset.step === '3' && step.dataset.category === 'docs');
          case 'complex': return steps.findIndex(step => step.dataset.step === '2.5');
          default: return currentStepIndex;
        }
      case '2':
        return steps.findIndex(step => step.dataset.step === '2.6');
      case '2-depix':
        return steps.findIndex(step => step.dataset.step === '2.6');
      case '2.6':
        return steps.findIndex(step => step.dataset.step === '2.7');
      case '2.5':
        if (['complex'].includes(selectedCategory)) {
          const categoryStep = steps.find(step => step.dataset.step === '3' && step.dataset.category === selectedCategory);
          return categoryStep ? steps.indexOf(categoryStep) : steps.findIndex(step => step.dataset.step === '4');
        } else {
          const profileType = formData.profileType;
          const profileStep = steps.find(step => step.dataset.step === '3' && step.dataset.profile === profileType);
          return profileStep ? steps.indexOf(profileStep) : -1;
        }
      case '3': return steps[currentStepIndex].querySelector('.submit-btn') ? -1 : steps.findIndex(step => step.dataset.step === '4');
      default: return -1;
    }
  }

  function determinePrevStep(currentStepIndex) {
    const currentStepDataSet = steps[currentStepIndex]?.dataset.step;
    switch (currentStepDataSet) {
      case '4':
        if (selectedCategory === 'onboarding') {
          return steps.findIndex(step => step.dataset.step === '2.7');
        }
        if (selectedCategory === 'security') {
          return steps.findIndex(step => step.dataset.step === '2.7');
        }
        if (selectedCategory === 'docs') {
          const docsStep = steps.find(step => step.dataset.step === '3' && step.dataset.category === 'docs');
          return docsStep ? steps.indexOf(docsStep) : steps.findIndex(step => step.dataset.step === '2.5');
        }
        if (['feature','general','complex','security','jobs'].includes(selectedCategory)) {
          const categoryStep = steps.find(step => step.dataset.step === '3' && step.dataset.category === selectedCategory);
          return categoryStep ? steps.indexOf(categoryStep) : steps.findIndex(step => step.dataset.step === '2.5');
        } else if (formData.profileType === 'usoProprio') {
          return steps.findIndex(step => step.dataset.step === '3' && step.dataset.profile === 'usoProprio');
        } else {
          const profileType = formData.profileType;
          const profileStep = steps.find(step => step.dataset.step === '3' && step.dataset.profile === profileType);
          return profileStep ? steps.indexOf(profileStep) : steps.findIndex(step => step.dataset.step === '2.5');
        }
      case '3':
        if (['security','general','feature'].includes(selectedCategory)) return steps.findIndex(step => step.dataset.step === '1');
        if (selectedCategory === 'jobs' || selectedCategory === 'docs') return steps.findIndex(step => step.dataset.step === '1');
        if (steps[currentStepIndex].dataset.profile === 'usoProprio') return steps.findIndex(step => step.dataset.step === '2');
        return steps.findIndex(step => step.dataset.step === '2.5');
      case '2.7':
        return steps.findIndex(step => step.dataset.step === '2.6');
      case '2.6':
        if (state.asset === 'depix') {
          const depixIndex = steps.findIndex(step => step.dataset.step === '2-depix');
          if (depixIndex !== -1) return depixIndex;
        }
        return steps.findIndex(step => step.dataset.step === '2');
      case '2.5':
        if (['feature','general','complex','security'].includes(selectedCategory)) return steps.findIndex(step => step.dataset.step === '1');
        return steps.findIndex(step => step.dataset.step === '2');
      case '2': return steps.findIndex(step => step.dataset.step === '1');
      case '2-depix':
        return steps.findIndex(step => step.dataset.step === '1');
      default: return -1;
    }
  }

  function setupFinalMessage() {
    if (!finalMessageContentContainer) return;
    finalMessageContentContainer.innerHTML = '';
    const amount = (formData.desiredAmountBRL || formData.desiredAmountBRL_DPX || (window.EulenState?.get()?.amount_brl ?? '') || '').replace(/\D/g,'');

    const brcodeInput = document.getElementById('pix-brcode');
    const brcodePreview = document.getElementById('pix-brcode-preview');
    const qrImg = document.getElementById('pix-qr-image');
    const copyBtn = document.getElementById('pix-copy');
    const statusEl = document.getElementById('pix-status');
    const simulateBtn = null;

    if (!brcodeInput || !qrImg || !copyBtn || !statusEl) return;

    let currentTxId = '';
    let currentPayload = '';

    copyBtn.onclick = () => {
      const toCopy = brcodeInput.value || currentPayload;
      if (toCopy) navigator.clipboard?.writeText(toCopy);
      const prevTitle = copyBtn.title;
      copyBtn.title = 'Copiado!';
      copyBtn.classList.add('copied');
      setTimeout(() => { copyBtn.title = prevTitle || 'Copiar'; copyBtn.classList.remove('copied'); }, 1200);
    };

    // Simulação desativada em produção

    initializePixSession();

    async function initializePixSession(){
      const valueBRL = amount ? Number(amount) : 0;
      const base = (window.CFTheme && CFTheme.baseUrl) ? CFTheme.baseUrl.replace(/\/$/, '') : '';
      const startUrl = base + '/api/pix/start';
      log('[PIX][start] url=', startUrl, 'payload=', { amountBRL: valueBRL, network: state.network, wallet: state.wallet });
      try { document.querySelector('.qr-box')?.classList.add('loading'); } catch(_){}
      try {
        const retryBtn = document.getElementById('pix-retry');
        if (retryBtn) retryBtn.style.display = 'none';
        const res = await fetch(startUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ amountBRL: valueBRL, network: state.network, wallet: state.wallet }) });
        log('[PIX][start] http=', res.status, res.statusText);
        const data = await res.json().catch(() => ({}));
        log('[PIX][start] json=', data);
        if (res.status === 202 || data?.async === true) { showPixError(data); return; }
        if (!res.ok) { showPixError(data); return; }
        if (!data || !data.txId) { showPixError('invalid start response'); return; }
        currentTxId = data.txId;
        applyPayload({ brcode: data.brcode, qrImageUrl: data.qrImageUrl });
        setStatus('waiting');
        attachStatusStream(currentTxId);
      } catch (err) {
        warn('[PIX][start][error]', err);
        showPixError(err && err.message ? err.message : String(err));
      }
      finally { try { document.querySelector('.qr-box')?.classList.remove('loading'); } catch(_){} }
    }

    function applyPayload(p){
      log('[PIX][payload]', p);
      if (!p) return;
      if (p.brcode) {
        currentPayload = p.brcode;
        brcodeInput.value = p.brcode;
        if (brcodePreview) brcodePreview.textContent = `${p.brcode.slice(0, 18)}...`;
      }
      if (p.qrImageUrl) {
        log('[PIX][qr] applying image');
        qrImg.src = p.qrImageUrl;
      } else {
        log('[PIX][qr] image not provided');
        qrImg.removeAttribute('src');
      }
    }

    function showPixError(raw){
      const msg = (raw && typeof raw === 'object')
        ? (raw.error ? String(raw.error) : JSON.stringify(raw))
        : (typeof raw === 'string' ? raw : 'Falha ao gerar QR. Tente novamente.');
      try { qrImg.removeAttribute('src'); } catch(_){ }
      setStatus('waiting');
      statusEl.textContent = msg;
      const retryBtn = document.getElementById('pix-retry');
      if (retryBtn) {
        retryBtn.style.display = '';
        retryBtn.onclick = () => { retryBtn.style.display = 'none'; initializePixSession(); };
      }
    }

    function setStatus(status){
      log('[PIX][status]', status);
      if (!status) return;
      statusEl.classList.remove('waiting','approved');
      if (status === 'approved') {
        statusEl.classList.add('approved');
        statusEl.textContent = 'Pagamento confirmado! Enviando ativos...';
        setTimeout(() => {
          // Ir para o slide de sucesso (5)
          try {
            const stepsEls = Array.from(document.querySelectorAll('.form-step'));
            const idx = stepsEls.findIndex(s => s.dataset.step === '5');
            if (idx !== -1) {
              // Configurar tutorial quando for fluxo BTC
              const currentAsset = (window.EulenState && typeof window.EulenState.get === 'function') ? (window.EulenState.get().asset || '') : '';
              const isBtc = (currentAsset === 'btc') || document.documentElement.classList.contains('asset-btc');
              const vWrap = document.getElementById('success-video');
              const iframe = document.getElementById('success-video-iframe');
              const hint = document.getElementById('success-hint');
              if (isBtc && vWrap && iframe) {
                vWrap.style.display = '';
                if (hint) hint.textContent = 'Veja como transformar DePix em Bitcoin pela SideSwap:';
                // URL do tutorial
                iframe.src = 'https://www.youtube.com/embed/rbxdFbSVOJk?rel=0&modestbranding=1';
              } else {
                if (vWrap) vWrap.style.display = 'none';
                if (iframe) iframe.src = '';
                if (hint) hint.textContent = 'Pagamento confirmado com sucesso.';
              }
              // Mostrar step 5
              const allSteps = Array.from(document.querySelectorAll('.form-step'));
              allSteps.forEach((step, i) => step.classList.toggle('active', i === idx));
              // Completar barra
              try { document.querySelector('.progress-bar .progress').style.width = '100%'; } catch(_){}
            }
          } catch(_){}
        }, 600);
      } else if (status === 'waiting') {
        statusEl.classList.add('waiting');
        statusEl.textContent = 'Aguardando pagamento...';
      } else if (status === 'expired') {
        statusEl.textContent = 'Tempo expirado. Gere outro QR.';
      } else if (status === 'failed') {
        statusEl.textContent = 'Falha no pagamento. Tente novamente.';
      }
    }

    function attachStatusStream(txId){
      log('[PIX][stream] attach for', txId);
      if (!txId) return;
      if (window.EventSource) {
        try {
          const es = new EventSource(`/api/pix/stream/${encodeURIComponent(txId)}`);
          es.onmessage = (e) => {
            try {
              const msg = JSON.parse(e.data || '{}');
              log('[PIX][stream][msg]', msg);
              if (msg.brcode || msg.qrImageUrl) applyPayload(msg);
              if (msg.status) {
                setStatus(msg.status);
                if (msg.status === 'approved' || msg.status === 'expired' || msg.status === 'failed') es.close();
              }
            } catch(_){ }
          };
          es.onerror = (e) => { warn('[PIX][stream][error]', e); es.close(); startPolling(txId); };
          return;
        } catch(_){ }
      }
      startPolling(txId);
    }

    function startPolling(txId){
      log('[PIX][poll] start for', txId);
      let attempts = 0;
      const iv = setInterval(async () => {
        attempts++;
        try {
          const base = (window.CFTheme && CFTheme.baseUrl) ? CFTheme.baseUrl.replace(/\/$/, '') : '';
          const url = `${base}/api/pix/status?txId=${encodeURIComponent(txId)}`;
          log('[PIX][poll] attempt', attempts, 'url=', url);
          const r = await fetch(url);
          const j = await r.json();
          log('[PIX][poll] resp=', j);
          if (j.brcode || j.qrImageUrl) applyPayload(j);
          if (j.status) {
            setStatus(j.status);
            if (j.status === 'approved' || j.status === 'expired' || j.status === 'failed') { clearInterval(iv); }
          }
        } catch(err){ warn('[PIX][poll][error]', err); if (attempts > 30) clearInterval(iv); }
      }, 2000);
    }

    // (simulações removidas)
  }

  document.querySelectorAll('.next-btn').forEach(button => {
    button.addEventListener('click', async () => {
      const currentDataSet = steps[currentStep]?.dataset.step;
      if (currentDataSet !== '1' && !validateStep(currentStep, true)) return;
      collectStepData(currentStep);
      if (currentDataSet === '2.5' && formData.category === 'onboarding') {
        console.log('[FORM] Creating ClickUp task for onboarding lead...');
        button.disabled = true; setNextButtonLoadingState(button, true);
        try {
          console.log('[FORM] Making request to /api...');
          const response = await fetch('/api', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(formData) });
          const result = await response.json();
          if (!result.success) { console.error('[FORM] Failed to create ClickUp task:', result.error); } else { console.log('[FORM] ClickUp task created successfully:', result.taskId); }
        } catch (error) {
          console.error('[FORM] Error calling ClickUp API:', error);
        } finally {
          button.disabled = false; setNextButtonLoadingState(button, false);
        }
      }
      const nextStepIndex = determineNextStep(currentStep);
      if (nextStepIndex !== -1 && nextStepIndex < steps.length) { showStep(nextStepIndex); }
      else if (nextStepIndex === -1 && currentDataSet === '3') { log('Next clicked on submitting Step 3.'); }
      else { setupFinalMessage(); showStep(steps.findIndex(step => step.dataset.step === '4')); }
    });
  });

  prevButtons.forEach(button => {
    button.addEventListener('click', () => {
      const prevStepIndex = determinePrevStep(currentStep);
      if (prevStepIndex !== -1) {
        showStep(prevStepIndex);
        try {
          const prevStepData = steps[prevStepIndex]?.dataset.step;
          if (prevStepData === '2.6') {
            const liq = document.getElementById('network-liquid');
            const ltn = document.getElementById('network-lightning');
            if (liq) liq.checked = false;
            if (ltn) ltn.checked = false;
          }
        } catch(_) {}
      }
    });
  });

  const formSubmitHandler = async (e) => {
    e.preventDefault(); log('Form submit event triggered!');
    if (!validateStep(currentStep, true)) { log('Submission prevented: validation failed.'); return; }
    collectStepData(currentStep); log('Final Form Data for Submission:', formData);
    if (formData.profileType === 'usoProprio') {
      alert('Obrigado pelo seu interesse! (Uso Próprio Flow)'); form.reset(); selectedCategory = ''; Object.keys(formData).forEach(key => delete formData[key]); showStep(0); const firstBtn = steps[0].querySelector('.next-btn'); if (firstBtn) { firstBtn.disabled = true; firstBtn.classList.add('disabled'); } return;
    }
    const submitBtns = form.querySelectorAll('.submit-btn'); submitBtns.forEach(btn => btn.disabled = true);
    // (simulação removida)
    submitBtns.forEach(btn => btn.disabled = false);
  };

  if (form) {
    form.setAttribute('novalidate', 'true');
    form.addEventListener('submit', formSubmitHandler);
  }

  const telegramInput = document.getElementById('telegramUser');
  if (telegramInput) {
    telegramInput.addEventListener('keypress', (e) => { if (e.key === '@') e.preventDefault(); });
    telegramInput.addEventListener('paste', (e) => { const pasteData = (e.clipboardData || window.clipboardData).getData('text'); if (pasteData.includes('@')) e.preventDefault(); });
  }

  const fieldsToValidateOnBlur = form.querySelectorAll('input[required], textarea[required], select[required]');
  fieldsToValidateOnBlur.forEach(field => {
    if (field.type === 'radio') {
      const radioGroup = form.querySelectorAll(`input[name="${field.name}"]`);
      radioGroup.forEach(radio => {
        if (!radio.hasAttribute('data-rtv-listener-added')) {
          radio.addEventListener('change', () => { validateInput(radio, true, true); const stepElement = radio.closest('.form-step'); const stepIndex = steps.indexOf(stepElement); if (stepIndex > -1) updateNextButtonState(stepIndex); });
          radio.setAttribute('data-rtv-listener-added', 'true');
        }
      });
    } else if (field.type === 'checkbox') {
      field.addEventListener('change', () => { validateInput(field, true, true); const stepElement = field.closest('.form-step'); const stepIndex = steps.indexOf(stepElement); if (stepIndex > -1) updateNextButtonState(stepIndex); });
    } else {
      field.addEventListener('blur', function() {
        const errorId = `${this.id || this.name}-error`; const errorElement = document.getElementById(errorId);
        if (this.required && !this.value.trim()) { this.classList.add('invalid'); if (errorElement) { errorElement.textContent = 'Este campo é obrigatório.'; errorElement.classList.add('visible'); errorElement.style.display = 'block'; } }
        else if (this.type === 'email' && this.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value)) { this.classList.add('invalid'); if (errorElement) { errorElement.textContent = 'Por favor, digite um e-mail válido.'; errorElement.classList.add('visible'); errorElement.style.display = 'block'; } }
        else if ((this.id === 'fullName' || this.id === 'telegramUser') && this.value.trim().length < 3) { this.classList.add('invalid'); if (errorElement) { errorElement.textContent = 'Deve ter no mínimo 3 caracteres.'; errorElement.classList.add('visible'); errorElement.style.display = 'block'; } }
        else { this.classList.remove('invalid'); if (errorElement) { errorElement.classList.remove('visible'); } }
        const stepElement = this.closest('.form-step'); const stepIndex = steps.indexOf(stepElement); if (stepIndex > -1) updateNextButtonState(stepIndex);
      });
      field.addEventListener('input', function() {
        const value = this.value.trim(); let isValid = true;
        if (this.required && !value) isValid = false;
        else if (this.type === 'email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value)) isValid = false;
        else if ((this.id === 'fullName' || this.id === 'telegramUser') && this.value.length < 3) isValid = false;
        if (isValid) { this.classList.remove('invalid'); const errorId = `${this.id || this.name}-error`; const errorElement = document.getElementById(errorId); if (errorElement) { errorElement.classList.remove('visible'); errorElement.style.display = 'none'; } }
        const stepElement = this.closest('.form-step'); const stepIndex = steps.indexOf(stepElement); if (stepIndex > -1) updateNextButtonState(stepIndex);
      });
    }
  });

  const consentCheckboxes = form.querySelectorAll('.checkbox-group input[type="checkbox"][required]');
  consentCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', () => { validateInput(checkbox, true, true); const stepElement = checkbox.closest('.form-step'); const stepIndex = steps.indexOf(stepElement); if (stepIndex > -1) updateNextButtonState(stepIndex); });
  });

  document.querySelectorAll('.error-message').forEach(msg => { msg.classList.remove('visible'); });
  document.querySelectorAll('.invalid').forEach(el => { el.classList.remove('invalid'); });
  document.querySelectorAll('.radio-group.invalid, .checkbox-group.invalid').forEach(group => { group.classList.remove('invalid'); });

  showStep(0);
});


