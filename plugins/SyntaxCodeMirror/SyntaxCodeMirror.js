require(["IGCMS"], function () {
  CodeMirrorInstance.on("change", function (cm, change) {
    if (!IGCMS.Editable) return;
    var form = IGCMS.Editable.getParentForm(CodeMirrorInstance.getTextArea());
    if (!form || !form.classList.contains(IGCMS.Editable.getEditableClass())) return;
    IGCMS.Editable.setModified();
  })
})
