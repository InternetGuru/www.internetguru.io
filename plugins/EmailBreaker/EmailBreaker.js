(function(win) {

  require("IGCMS", function () {

    var Config = {};
    Config.rep = [];

    var EmailBreaker = function () {

      // private
      var
        createEmails = function () {
          var spans = document.getElementsByTagName("span");
          var emails = [];
          for (var i = 0; i < spans.length; i++) {
            if (!spans[i].classList.contains("emailbreaker")) continue;
            emails.push(spans[i]);
          }
          for (var i = 0; i < emails.length; i++) {
            var addrs = emails[i].getElementsByTagName("span");
            for (var j = 0; j < addrs.length; j++) {
              if (!addrs[j].classList.contains("addr")) continue;
              createEmailLink(emails[i], addrs[j]);
            }
          }
        },
        createEmailLink = function (span, addr) {
          var a = document.createElement("a");
          for (var i = 0; i < span.attributes.length; i++) {
            var attr = span.attributes.item(i);
            a.setAttribute(attr.nodeName, attr.nodeValue);
          }
          a.className = span.className;
          var email = addr.textContent;
          for (var i = 0; i < Config.rep.length; i++) {
            email = email.replace(new RegExp(IGCMS.preg_quote(Config.rep[i][1]), "g"), Config.rep[i][0]);
          }
          a.href = "mailto:" + email.replace(" ", "");
          if (addr.classList.contains("del")) addr.parentNode.removeChild(addr);
          else addr.textContent = email;
          while (span.childNodes.length > 0) {
            a.appendChild(span.childNodes[0]);
          }
          span.parentNode.insertBefore(a, span);
          span.parentNode.removeChild(span);
        };

      // public
      return {
        init: function (cfg) {
          IGCMS.initCfg(Config, cfg);
          createEmails();
        }
      }
    };

    win.IGCMS.EmailBreaker = new EmailBreaker();
  })
})(window)
