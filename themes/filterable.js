(function(win){

  var Config = {}
  Config.initClass = "filterable";
  Config.classPrefix = "filterable-";
  Config.close = "×";

  Filterable = function() {
    var
    that = this,
    initCfg = function(cfg) {
      if(typeof cfg === 'undefined') return;
      for(var attr in cfg) {
        if(!Config.hasOwnProperty(attr)) continue;
        Config[attr] = cfg[attr];
      }
    },
    initFilters = function() {
      var dls = document.querySelectorAll("." + Config.initClass);
      for(var i = 0; i < dls.length; i++) {
        var fDl = new FilterableDl(dls[i]);
        fDl.init();
      }
    }
    return {
      init : function(cfg) {
        initCfg(cfg);
        initFilters();
      }
    }
  }

  FilterableDl = function(dl) {
    var
    that = this,
    wrapper = dl,
    tags = [],
    rows = [],
    collectionHas = function(a, b) {
      for(var i = 0, len = a.length; i < len; i ++) {
        if(a[i] == b) return true;
      }
      return false;
    },
    findParentBySelector = function(elm, selector) {
      var all = document.querySelectorAll(selector);
      var cur = elm;
      while(cur && !collectionHas(all, cur)) { //keep going up until you find a match
        cur = cur.parentNode; //go up
      }
      return cur; //will return null if not found
    },
    createKw = function(dd, kw) {
      var a = document.createElement("a");
      a.href = "#" + kw;
      a.textContent = kw;
      var info = document.createElement("span");
      info.textContent = "1";
      var span = document.createElement("span");
      span.className = Config.classPrefix+"tag";
      span.dataset.value = kw;
      span.appendChild(a);
      span.appendChild(info);
      if(!(kw in tags)) tags[kw] = [];
      return {tag: span, info: info, size: 1, a: a, dd: dd};
    }
    loadKws = function() {
      var dds = dl.querySelectorAll("dd.kw");
      for(var i = 0; i < dds.length; i++) {
        var kws = dds[i].textContent.split(",").map(function(s) { return s.replace(/^\s\s*/, '').replace(/\s\s*$/, '') });
        dds[i].innerHTML = "";
        kwsObjects = [];
        var vals = [];
        for(var j = 0; j < kws.length; j++) {
          if(vals.indexOf(kws[j]) != -1) continue; // ignore duplicits
          kw = createKw(dds[i], kws[j]);
          kwsObjects.push(kw);
          tags[kws[j]].push(kw);
          vals.push(kws[j]);
        }
        rows.push({ dd: dds[i], kws: kwsObjects });
      }
    },
    initKws = function() {
      for(kw in tags) {
        for(i in tags[kw]) {
          if(tags[kw].length > 1) {
            tags[kw][i].info.textContent = tags[kw].length;
            tags[kw][i].size = tags[kw].length;
            tags[kw][i].tag.addEventListener("click", filter, false);
          } else {
            tags[kw][i].info.parentNode.removeChild(tags[kw][i].info);
            tags[kw][i].a.onclick = function() { return false };
            tags[kw][i].tag.classList.add(Config.classPrefix+"inactive");
          }
        }
      }
    },
    sortKws = function() {
      for(i in rows) {
        rows[i].kws.sort(function(a, b){ return b.info.textContent - a.info.textContent });
        for(j in rows[i].kws) {
          rows[i].dd.appendChild(rows[i].kws[j].tag);
        }
      }
    },
    /*removeDuplicit = function() {
      for(i in rows) {
        var values = [];
        for(j in rows[i].kws) {
          if(values.indexOf(rows[i].kws[j].tag.dataset.value) != -1) {
            rows[i].kws[j].tag.parentNode.removeChild(rows[i].kws[j].tag);
            continue;
          }
          values.push(rows[i].kws[j].tag.dataset.value);
        }
      }
    },*/
    initStrucure = function() {
      loadKws();
      initKws();
      sortKws();
      //removeDuplicit();
    },
    clearFilter = function() {
      var dds = wrapper.getElementsByTagName("dd");
      for(var i = 0; i < dds.length; i++) { dds[i].className = "" }
      var dts = wrapper.getElementsByTagName("dt");
      for(var i = 0; i < dts.length; i++) { dts[i].className = "" }
      for(kw in tags) {
        if(tags[kw].length > 1) for(i in tags[kw]) {
          deactivate(tags[kw][i].tag);
          tags[kw][i].info.textContent = tags[kw][i].size;
        }
      }
    },
    hideRow = function(el) {
      var e = el;
      while(e.nodeName.toLowerCase() == "dd") {
        e.className = "hide";
        e = e.previousSibling;
      }
      e.previousSibling.className = "hide";
    },
    removeFilter = function(e) {
      clearFilter();
      deactivate(findParentBySelector(e.target, "."+Config.classPrefix+"tag"));
    },
    deactivate = function(el) {
      el.classList.remove(Config.classPrefix+"active");
      el.addEventListener("click", filter, false);
      el.removeEventListener("click", removeFilter, false);
    },
    activate = function(el) {
      el.classList.add(Config.classPrefix+"active");
      el.removeEventListener("click", filter, false);
      el.addEventListener("click", removeFilter, false);
    },
    filter = function(e) {
      var value = findParentBySelector(e.target, "."+Config.classPrefix+"tag").dataset.value;
      doFilter(value);
    },
    doFilter = function(value) {
      clearFilter();
      var hides = 0;
      for(i in rows) {
        var found = false;
        for(j in rows[i].kws) {
          if(rows[i].kws[j].tag.dataset.value != value) continue;
          found = true;
          break;
        }
        if(!found) {
          hideRow(rows[i].dd);
          hides++;
        }
      }
      if(hides == rows.length) { // not found
        clearFilter();
        return;
      }
      for(kw in tags) {
        if(kw != value) continue;
        for(i in tags[kw]) {
          activate(tags[kw][i].tag);
          tags[kw][i].info.textContent = "×";
        }
      }
    },
    filterHash = function(e) {
      var hash = window.location.hash ? window.location.hash.substring(1) : "";
      if(hash.length) doFilter(hash);
    }
    return {
      init : function() {
        initStrucure();
        win.addEventListener("load", filterHash, false);
      }
    }
  }

  filterable = new Filterable();
  win.Filterable = filterable;
  filterable.init();

    // append style
  var css = '/* filterable.js */'
    + '.filterable .'+Config.classPrefix+'tag {display: inline-block; border: 0; margin: 0.1em; border-radius: 0.15em; border: 0.1em solid #aaa; color: #000; cursor: pointer; }'
    + '.filterable .'+Config.classPrefix+'tag span {display: inline-block; padding: 0.25em 0.5em; border-left: 0.1em solid #ddd; }'
    + '.filterable .'+Config.classPrefix+'inactive span {border-color: #eee; }'
    + '.filterable .'+Config.classPrefix+'inactive {border-color: #eee; color: #555; cursor: text; }'
    + '.filterable .'+Config.classPrefix+'active {background-color: #8E8E8E; color: white; border-color: #4E4E4E; }'
    + '.filterable .'+Config.classPrefix+'active span {border-color: #A4A4A4; }'
    + '.filterable .'+Config.classPrefix+'tag a {border: 0 none; color: #000 !important; display: inline-block; padding: 0.25em 0.5em; }'
    + '.filterable .'+Config.classPrefix+'inactive a {color: #555 !important; cursor: text; }'
    + '.filterable .'+Config.classPrefix+'active a {color: white !important; }';
  var style = document.getElementsByTagName('style')[0];
  if(style == undefined) {
    var head = document.head || document.getElementsByTagName('head')[0];
    style = document.createElement('style');
    style.type = 'text/css';
    head.appendChild(style);
  }
  style.appendChild(document.createTextNode(css));

})(window);