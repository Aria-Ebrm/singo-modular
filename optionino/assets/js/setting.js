document.addEventListener("DOMContentLoaded", function() {
  const tabContents = document.querySelectorAll('.tabcontent');
  const tabLinks = document.querySelectorAll('.tablinks');
  if (tabContents.length > 0 && tabLinks.length > 0) {
    tabContents[0].style.display = "block";
    tabLinks[0].classList.add("active");
  }

  function unq(s){
    if (s == null) return "";
    s = String(s).replace(/&quot;/g, '"').trim();
    if (s[0] === '"' && s[s.length-1] === '"') s = s.slice(1,-1);
    return s;
  }
  function parseMaybeJSON(raw){
    if (raw == null || raw === "") return "";
    const str = unq(raw);
    try { return JSON.parse(str); } catch(e) { return str; }
  }
  function readControlValue(fieldId){
    try {
      var maybe = JSON.parse(String(fieldId).replace(/&quot;/g,'"'));
      if (Array.isArray(maybe) && maybe.length >= 1 && typeof maybe[0] === 'string') fieldId = maybe[0];
    } catch(e){}
    var radio = document.querySelector('input[name="'+fieldId+'"]:checked');
    if (radio) return String(radio.value);
    var el = document.getElementById(fieldId);
    if (!el) return "";
    if (el.type === "checkbox") return el.checked;
    return (el.value != null) ? String(el.value) : "";
  }
  function eqLike(a,b){
    if (typeof b === "boolean") return (typeof a === "boolean" ? a : (String(a)==="true")) === b;
    return String(a) === String(b);
  }
  function neqLike(a,b){
    if (typeof b === "boolean") return !eqLike(a,b);
    return String(a) !== String(b);
  }
  function matchCondition(fieldId, opRaw, reqRaw){
    let op = unq(opRaw || "=").toLowerCase();
    if (op === "==") op = "=";
    if (op === "in") op = "or";
    const cur = readControlValue(fieldId);
    const req = parseMaybeJSON(reqRaw);
    if (op === "=")  return eqLike(cur, req);
    if (op === "!=") return neqLike(cur, req);
    if (op === "or") {
      const arr = Array.isArray(req) ? req : [req];
      return arr.map(String).includes(String(cur));
    }
    return eqLike(cur, req);
  }
  function getRequireTriples(box){
    const jr = box.getAttribute('data-require');
    if (jr) {
      let parsed = parseMaybeJSON(jr);
      if (Array.isArray(parsed) && Array.isArray(parsed[0])) {
        return parsed.map(function(r){ return [String(r[0]||''), String((r[1]||'=')).toLowerCase(), (typeof r[2]==='string'? r[2] : JSON.stringify(r[2]))]; });
      }
      if (Array.isArray(parsed)) {
        return [[String(parsed[0]||''), String((parsed[1]||'=')).toLowerCase(), (typeof parsed[2]==='string'? parsed[2] : JSON.stringify(parsed[2]))]];
      }
      if (parsed && parsed.rules && Array.isArray(parsed.rules)) {
        return parsed.rules.map(function(r){ return [String(r[0]||''), String((r[1]||'=')).toLowerCase(), (typeof r[2]==='string'? r[2] : JSON.stringify(r[2]))]; });
      }
    }
    var triples = [];
    var attrs = Array.prototype.slice.call(box.attributes || []);
    var indexedRequires = attrs
      .map(function(attr){
        var m = /^data-require-(\d+)$/.exec(attr.name);
        if (!m) return null;
        return { idx: parseInt(m[1], 10), raw: attr.value };
      })
      .filter(function(item){ return item !== null; })
      .sort(function(a,b){ return a.idx - b.idx; });

    indexedRequires.forEach(function(item){
      var parsed = null;
      try { parsed = JSON.parse(String(item.raw).replace(/&quot;/g, '"')); } catch(e){}
      if (Array.isArray(parsed)) {
        var f = String(parsed[0] || '');
        var op = String((parsed[1] || '=')).toLowerCase();
        var v = parsed[2];
        var vRaw = (typeof v === 'string') ? v : JSON.stringify(v);
        triples.push([f, op, vRaw]);
      }
    });

    if (triples.length) return triples;

    var r0 = box.getAttribute('data-require-0');
    if (!r0) return [];
    var opAttr = box.getAttribute('data-require-1') || '"="';
    var vAttr = box.getAttribute('data-require-2') || '""';
    var f2 = String(r0).replace(/&quot;/g, '"').trim();
    if (f2[0] === '"' && f2[f2.length - 1] === '"') f2 = f2.slice(1, -1);
    var op2 = String(opAttr).replace(/&quot;/g, '"').trim();
    if (op2[0] === '"' && op2[op2.length - 1] === '"') op2 = op2.slice(1, -1);
    return [[f2, op2, vAttr]];
  }
  function updateFieldVisibility(box){
    const triples = getRequireTriples(box);
    if (!triples.length) return;
    let visible = true;
    for (let i=0;i<triples.length;i++){
      const t = triples[i];
      if (!matchCondition(t[0], t[1], t[2])) { visible = false; break; }
    }
    box.setAttribute('display', visible ? 'true' : 'false');
    box.style.display = visible ? 'flex' : 'none';
  }
  function applyConditions(){
    const boxes = document.querySelectorAll('.optionino-conditional-option[data-require], .optionino-conditional-option[data-require-0]');
    if (!boxes.length) return;
    boxes.forEach(updateFieldVisibility);
  }

  const OPTNNO = document.getElementById('optionino');
  const form = document.getElementById('save-options-optionino');
  if (form) {
    const errorText = form.querySelector('.error-text');
    const successText = form.querySelector('.success-text');
    form.addEventListener('submit', function(event) {
      event.preventDefault();
      if (OPTNNO) OPTNNO.classList.add('loading');
      if (errorText) errorText.style.display = 'none';
      if (successText) successText.style.display = 'none';

      let formData = new FormData(form);
      const checkboxes = form.querySelectorAll('input[type="checkbox"]');
      checkboxes.forEach(function(checkbox){
        const checkboxName = checkbox.name;
        if (!formData.has(checkboxName)) formData.set(checkboxName, 'off');
      });

      const repeaterFields = document.querySelectorAll('.optionino-repeater-field[data-repeater-name]');
      repeaterFields.forEach(function(repeaterField){
        const repeaterName = repeaterField.getAttribute('data-repeater-name');
        const repeaterValues = collectRepeaterValues(repeaterField);
        formData.append(repeaterName, repeaterValues);
      });

      formData.append('action', 'save_optionino_data');

      // Robust fallback for nonce and ajax_url
      const nonceVal = (window.data_optionino && window.data_optionino.nonce) ? window.data_optionino.nonce : (form.querySelector('input[name="security"]')?.value || '');
      formData.append('security', nonceVal);

      const targetUrl = (window.data_optionino && window.data_optionino.ajax_url) ? window.data_optionino.ajax_url : (window.ajaxurl || 'admin-ajax.php');

      const xhr = new XMLHttpRequest();
      xhr.open('POST', targetUrl, true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.onload = function() {
        if (OPTNNO) OPTNNO.classList.remove('loading');
        let response = {};
        try { response = JSON.parse(xhr.responseText || '{}'); } catch(e){}
        if (xhr.status === 200) {
          if (response.success) {
            if (successText) { successText.textContent = (response.data && response.data.message) || 'تنظیمات با موفقیت ذخیره شد.'; successText.style.display = 'block'; }
          } else {
            if (errorText) { errorText.textContent = (response.data && response.data.message) || 'خطا در ذخیره‌سازی اطلاعات.'; errorText.style.display = 'block'; }
          }
        } else {
          if (errorText) { errorText.textContent = "خطای ارتباط با سرور (کد: " + xhr.status + "). لطفاً مجدداً تلاش کنید."; errorText.style.display = 'block'; }
        }
      };
      xhr.onerror = function() {
        if (OPTNNO) OPTNNO.classList.remove('loading');
        if (errorText) { errorText.textContent = "خطای شبکه رخ داده است. لطفاً اتصال اینترنت خود را بررسی نمایید."; errorText.style.display = 'block'; }
      };
      xhr.send(formData);
    });

    function collectRepeaterValues(repeaterField){
      const repeaterItems = repeaterField.querySelectorAll('.optionino-repeater-item');
      const mainArray = [];
      repeaterItems.forEach(function(repeaterItem){
        const subArray = {};
        repeaterItem.querySelectorAll('input, textarea, select').forEach(function(input){
          subArray[input.name.replace(/_\d+$/, '').replace(/^_/, '')] = input.value;
        });
        mainArray.push(encodeRepeaterValues(subArray));
      });
      return encodeRepeaterValues(mainArray);
    }

    function encodeRepeaterValues(values){
      const params = new URLSearchParams();
      for (const key in values) {
        if (Object.prototype.hasOwnProperty.call(values, key)) {
          if (Array.isArray(values[key])) {
            params.append(key, JSON.stringify(values[key]));
          } else {
            params.append(key, values[key]);
          }
        }
      }
      return params.toString();
    }
  }

  function updateAll(){ applyConditions(); }
  updateAll();
  document.addEventListener('change', updateAll, true);
  const mo = new MutationObserver(function(){ updateAll(); });
  mo.observe(document.body, {subtree:true, childList:true, attributes:true, attributeFilter:['checked','value']});
});
