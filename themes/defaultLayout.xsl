<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:template match="/body">
    <body>
      <xsl:copy-of select="@*"/>
      <div id="header">
        <xsl:choose>
          <xsl:when test="count(ol[contains(@class,'cms-breadcrumb')]/li)>1">
            <xsl:copy-of select="ol[contains(@class,'cms-breadcrumb')]"/>
          </xsl:when>
          <xsl:otherwise>
            <xsl:text disable-output-escaping="yes">&lt;!-- empty --&gt;</xsl:text>
          </xsl:otherwise>
        </xsl:choose>
      </div>
      <div id="content">
        <xsl:apply-templates select="*[not(self::ol[contains(@class,'cms-breadcrumb')] or self::ul[contains(@class,'cms-menu')])]" />
      </div>
      <div id="footer">
        <xsl:copy-of select="ul[contains(@class,'cms-menu')]"/>
        <ul><li>©2014 internetguru.cz</li></ul>
      </div>
    </body>
  </xsl:template>

  <!-- <xsl:template name="menu_link">
    <ul>
    <xsl:for-each select="//h2">
      <li>
        <xsl:element name="a">
          <xsl:attribute name="href">#<xsl:value-of select="@id"/></xsl:attribute>
          <xsl:value-of select="@title"/>
        </xsl:element>
      </li>
    </xsl:for-each>
    </ul>
  </xsl:template> -->

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>