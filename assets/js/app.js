// =============================================================
// assets/js/app.js — Máscaras, CEP, validações front-end
// =============================================================

'use strict';

// ---- Máscaras de input ----
const Mascara = {
  cpf(el) {
    let v = el.value.replace(/\D/g, '').substring(0, 11);
    if (v.length > 9)      v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
    else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
    el.value = v;
  },

  rg(el) {
    let v = el.value.replace(/\D/g, '').substring(0, 9);
    if (v.length > 8)      v = v.replace(/(\d{2})(\d{3})(\d{3})(\d{1})/, '$1.$2.$3-$4');
    else if (v.length > 5) v = v.replace(/(\d{2})(\d{3})(\d{1,3})/, '$1.$2.$3');
    el.value = v;
  },

  data(el) {
    let v = el.value.replace(/\D/g, '').substring(0, 8);
    if (v.length > 4)      v = v.replace(/(\d{2})(\d{2})(\d{1,4})/, '$1/$2/$3');
    else if (v.length > 2) v = v.replace(/(\d{2})(\d{1,2})/, '$1/$2');
    el.value = v;
  },

  cep(el) {
    let v = el.value.replace(/\D/g, '').substring(0, 8);
    if (v.length > 5) v = v.replace(/(\d{5})(\d{1,3})/, '$1-$2');
    el.value = v;
  },

  telefone(el) {
    let v = el.value.replace(/\D/g, '').substring(0, 11);
    if (v.length > 10) v = v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    else if (v.length > 6) v = v.replace(/(\d{2})(\d{4,5})(\d{0,4})/, '($1) $2-$3');
    else if (v.length > 2) v = v.replace(/(\d{2})(\d*)/, '($1) $2');
    el.value = v;
  },
};

// ---- Verificação de menor de idade ----
function verificarMenorIdade() {
  const dtInput = document.getElementById('data_nascimento');
  if (!dtInput) return;

  const val = dtInput.value;
  const match = val.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
  if (!match) return;

  const nasc  = new Date(match[3], match[2] - 1, match[1]);
  const hoje  = new Date();
  let idade   = hoje.getFullYear() - nasc.getFullYear();
  const mDiff = hoje.getMonth() - nasc.getMonth();
  if (mDiff < 0 || (mDiff === 0 && hoje.getDate() < nasc.getDate())) idade--;

  const alertMenor  = document.getElementById('alert-menor');
  const tabResp     = document.getElementById('tab-responsavel');

  if (idade < 18 && idade >= 0 && idade <= 120) {
    if (alertMenor) alertMenor.style.display = 'flex';
    if (tabResp)    tabResp.classList.remove('disabled');
    document.getElementById('is_menor')?.setAttribute('value', '1');
  } else {
    if (alertMenor) alertMenor.style.display = 'none';
    if (tabResp)    tabResp.classList.add('disabled');
    document.getElementById('is_menor')?.setAttribute('value', '0');
  }
}

// ---- Busca de CEP ----
async function buscarCEP(prefixo = '') {
  const cepInput = document.getElementById(prefixo + 'cep');
  if (!cepInput) return;

  const cep = cepInput.value.replace(/\D/g, '');
  if (cep.length !== 8) return;

  const statusEl = document.getElementById(prefixo + 'cep-status');
  const msgEl    = document.getElementById(prefixo + 'cep-msg');

  if (statusEl) { statusEl.classList.add('active'); }
  if (msgEl)    { msgEl.textContent = 'Buscando endereço...'; }

  try {
    const resp = await fetch(`/api/cep/${cep}`);
    if (!resp.ok) throw new Error('CEP não encontrado');

    const dados = await resp.json();
    if (dados.erro) throw new Error('CEP não encontrado');

    const set = (id, val) => {
      const el = document.getElementById(prefixo + id);
      if (el && val) el.value = val;
    };

    set('endereco', dados.logradouro);
    set('bairro',   dados.bairro);
    set('cidade',   dados.cidade);
    set('uf',       dados.uf);

    if (msgEl) {
      msgEl.textContent = '✓ Endereço encontrado';
      statusEl?.classList.remove('active');
      statusEl?.classList.add('active');
    }

    setTimeout(() => { if (statusEl) statusEl.classList.remove('active'); }, 2000);

  } catch (err) {
    if (msgEl) msgEl.textContent = '⚠ CEP não encontrado. Preencha manualmente.';
    setTimeout(() => { if (statusEl) statusEl.classList.remove('active'); }, 2500);
  }
}

// ---- Tabs ----
function switchTab(tab) {
  document.querySelectorAll('.tab-item').forEach(t => {
    t.classList.toggle('active', t.dataset.tab === tab);
  });
  document.querySelectorAll('.tab-pane').forEach(p => {
    p.style.display = p.dataset.pane === tab ? 'block' : 'none';
  });
}

// ---- Toggle campo condicional (condição clínica) ----
function toggleCondicao(radioEl, targetId) {
  const target = document.getElementById(targetId);
  if (!target) return;
  const show = radioEl.value === 'sim';
  target.style.display = show ? 'block' : 'none';
  target.required = show;
  if (!show) target.value = '';

  // Atualiza visual dos radio-btns
  const group = radioEl.closest('.radio-group');
  group?.querySelectorAll('.radio-btn').forEach(btn => {
    btn.classList.toggle('selected', btn.dataset.value === radioEl.value);
  });
}

// ---- Menu mobile ----
function toggleSidebar() {
  document.querySelector('.sidebar')?.classList.toggle('open');
}

// ---- Flash messages auto-hide ----
function autoHideFlash() {
  document.querySelectorAll('.alert-success, .alert-info').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity 0.5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });
}

// ---- Init ----
document.addEventListener('DOMContentLoaded', () => {
  // Máscaras
  document.querySelectorAll('[data-mask="cpf"]').forEach(el => {
    el.addEventListener('input', () => Mascara.cpf(el));
  });
  document.querySelectorAll('[data-mask="rg"]').forEach(el => {
    el.addEventListener('input', () => Mascara.rg(el));
  });
  document.querySelectorAll('[data-mask="data"]').forEach(el => {
    el.addEventListener('input', () => { Mascara.data(el); verificarMenorIdade(); });
  });
  document.querySelectorAll('[data-mask="cep"]').forEach(el => {
    el.addEventListener('input', () => Mascara.cep(el));
    el.addEventListener('blur',  () => buscarCEP(el.dataset.prefix || ''));
  });
  document.querySelectorAll('[data-mask="telefone"]').forEach(el => {
    el.addEventListener('input', () => Mascara.telefone(el));
  });

  // Tabs
  document.querySelectorAll('.tab-item').forEach(tab => {
    tab.addEventListener('click', () => switchTab(tab.dataset.tab));
  });

  // Radio btns condição clínica
  document.querySelectorAll('.radio-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.target;
      const value  = btn.dataset.value;
      if (target) toggleCondicao({ value, closest: () => btn.closest('.radio-group') }, target);

      btn.closest('.radio-group')?.querySelectorAll('.radio-btn').forEach(b => {
        b.classList.toggle('selected', b === btn);
      });

      const input = document.getElementById(target);
      if (input) {
        input.style.display = value === 'sim' ? 'block' : 'none';
      }
    });
  });

  autoHideFlash();
});
