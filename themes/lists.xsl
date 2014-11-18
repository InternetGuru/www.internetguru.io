<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:template match="/">
    <xsl:apply-templates/>
  </xsl:template>

  <xsl:template match="//*[(parent::body or parent::div[contains(@class,'section')]) and (self::ul or self::ol or self::dl)]">
    <xsl:variable name="nm" select="name()"/>

    <xsl:choose>
      <!-- first list from a group of lists -->
      <xsl:when test="not(preceding-sibling::*[1][name() = $nm])
        and following-sibling::*[1][name() = $nm]">
        <xsl:text disable-output-escaping="yes">&lt;div class="list multiple">&lt;div></xsl:text>
        <xsl:element name="{name()}">
          <xsl:copy-of select="@*"/>
          <xsl:apply-templates/>
        </xsl:element>
      </xsl:when>
      <!-- last list from a group of lists -->
      <xsl:when test="preceding-sibling::*[1][name() = $nm]
        and not(following-sibling::*[1][name() = $nm])">
        <xsl:element name="{name()}">
          <xsl:copy-of select="@*"/>
          <xsl:apply-templates/>
        </xsl:element>
        <xsl:text disable-output-escaping="yes">&lt;/div>&lt;/div></xsl:text>
      </xsl:when>
      <!-- orphan list -->
      <xsl:when test="not(preceding-sibling::*[1][name() = $nm])
        and not(following-sibling::*[1][name() = $nm])">
        <div class="list">
          <xsl:element name="{name()}">
            <xsl:copy-of select="@*"/>
            <xsl:apply-templates/>
          </xsl:element>
        </div>
      </xsl:when>
      <xsl:otherwise>
        <xsl:element name="{name()}">
          <xsl:copy-of select="@*"/>
          <xsl:apply-templates/>
        </xsl:element>
      </xsl:otherwise>
    </xsl:choose>

  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>