<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

  <xsl:param name="cms-admin_id" select="''"/>
  <xsl:param name="cms-logged_user" select="''"/>
  <xsl:param name="cms-super_user" select="''"/>

  <xsl:template match="/">
    <xsl:apply-templates/>
  </xsl:template>

  <xsl:template match="div[contains(@id, 'footer')]/ul[last()]">
    <xsl:if test="not($cms-super_user = '') and not($cms-super_user = 'server')">
      <ul>
        <li><a href="?admin">Administrace</a></li>
        <li><a href="?log">Logy</a></li>
        <li><a href="?ver">Verze</a></li>
        <li><a href="?import">Import</a></li>
        <li><a href="?subdom">Poddomény</a></li>
      </ul>
    </xsl:if>
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>

  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>
