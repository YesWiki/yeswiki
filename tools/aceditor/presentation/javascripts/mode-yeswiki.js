ace.define("ace/mode/yeswiki_highlight_rules",["require","exports","module","ace/lib/oop","ace/mode/text_highlight_rules", "ace/mode/html_highlight_rules"], function(require, exports, module) {
"use strict";

var oop = require("../lib/oop");
var TextHighlightRules = require("./text_highlight_rules").TextHighlightRules;
var HtmlHighlightRules = require("./html_highlight_rules").HtmlHighlightRules;

var YesWikiHighlightRules = function() {

    this.$rules = {
        "start" : [{
            token : "markup.yw-action.open",
            regex : "\\{\\{",
            next: "yw-action"
        }, {
            token: "markup.html",
            regex: "\"\"",
            next: "html-start"
        }, {
            token : "constant.language.escape",
            regex : /\\[\\`*_{}\[\]()#+\-.!]/
        },{ // pre //
            token : "markup.pre",
            regex : "([%]{2})",
            next: "pre-start"
        },{ // headings //
            token : "markup.headings.1",
            regex : "([=]{6}(?=\\S))(.*?\\S[=]*)(\\1)"
        },{ // headings //
            token : "markup.headings.2",
            regex : "([=]{5}(?=\\S))(.*?\\S[=]*)(\\1)"
        },{ // headings //
            token : "markup.headings.3",
            regex : "([=]{4}(?=\\S))(.*?\\S[=]*)(\\1)"
        },{ // headings //
            token : "markup.headings.4",
            regex : "([=]{3}(?=\\S))(.*?\\S[=]*)(\\1)"
        },{ // headings //
            token : "markup.headings.5",
            regex : "([=]{2}(?=\\S))(.*?\\S[=]*)(\\1)"
        },{
            token : "empty_line",
            regex : '^$',
            next: "allowBlock"
        },
        { // HR ----
            token : "constant.hr",
            regex : "^[-]{3,50}$",
            next: "allowBlock"
        }, { // list
            token : "markup.list",
            regex : "^\\s{1,3}(?:-|\\d+\\.)\\s+",
            next  : "listblock-start"
        }, {
            include : "basic", noEscape: true
        }],
        "basic": [
        { // strong ** __
            token : "bold",
            regex : "([*]{2}(?=.*?))(.*?[*]*)(\\1)"
        }, { // italic //
            token : "italic",
            regex : "([/]{2}(?=.*?))(.*?[/]*)(\\1)"
        }, { // underline //
            token : "underline",
            regex : "([_]{2}(?=.*?))(.*?[_]*)(\\1)"
        }, { // stroke //
            token : "stroke",
            regex : "([@]{2}(?=.*?))(.*?[@]*)(\\1)"
        }, { // link
            token : ["markup", "underline.link", "text", "string", "markup"],
            regex : "(\\[\\[)([^\\s]+)(\\s)(.*)(\\]\\])"
        }
        ],
        "allowBlock": [
            {token : "support.function", regex : "^ {4}.+", next : "allowBlock"},
            {token : "empty_line", regex : '^$', next: "allowBlock"},
            {token : "empty", regex : "", next : "start"}
        ],
        "yw-action" : [{
            token: "meta.tag.yw-action",
            regex: "[a-zA-Z0-9-_]+\\s?",
            next: "yw-action-attributes"
        }],
        "yw-action-attributes": [{
            token : ["entity.other.attribute-name.xml.yw-action", "equal", "string.attribute-value.xml.yw-action"],
            regex : "([-_a-zA-Z0-9]+)(=)(\"[^\"]*\")"
        },{
            token: "markup.yw-action.end",
            regex: "\\}\\}",
            next: "start"
        }],
        "listblock-start" : [{
            token : "support.variable",
            regex : /(?:\[[ x]\])?/,
            next  : "listblock"
        }],
        "listblock" : [{ // Lists only escape on completely blank lines.
            token : "empty_line",
            regex : "^$",
            next  : "start"
        }, { // list
            token : "markup.list",
            regex : "^\\s{0,3}(?:[*+-]|\\d+\\.)\\s+",
            next  : "listblock-start"
        }, {
            include : "basic", noEscape: true
        }],
        "pre-start" : [
        { // pre //
            token : "markup.pre",
            regex : "([%]{2})",
            next: "start"
        },
        {
            token: "pre",
            regex: "[^%]*"
        }]
    };

    this.embedRules(HtmlHighlightRules, "html-", [
        {
            token: "markup.html",
            regex: "\"\"",
            next: "start"
        }
    ]);

    this.normalizeRules();
};

oop.inherits(YesWikiHighlightRules, TextHighlightRules);

exports.YesWikiHighlightRules = YesWikiHighlightRules;
});

ace.define("ace/mode/yeswiki",["require","exports","module","ace/lib/oop","ace/mode/text","ace/mode/yeswiki_highlight_rules"], function(require, exports, module) {
"use strict";

var oop = require("../lib/oop");
var TextMode = require("./text").Mode;
var YesWikiHighlightRules = require("./yeswiki_highlight_rules").YesWikiHighlightRules;

var Mode = function() {
    this.HighlightRules = YesWikiHighlightRules;
    this.$behaviour = this.$defaultBehaviour;
};
oop.inherits(Mode, TextMode);

(function() {
    this.type = "text";
    this.blockComment = {start: '""<!--', end: '-->""'};
    this.$quotes = {'"': '"', "`": "`"};

    this.getNextLineIndent = function(state, line, tab) {
        if (state == "listblock") {
            var match = /^(\s*)(?:([-+*])|(\d+)\.)(\s+)/.exec(line);
            if (!match)
                return "";
            var marker = match[2];
            if (!marker)
                marker = parseInt(match[3], 10) + 1 + ".";
            return match[1] + marker + match[4];
        } else {
            return this.$getIndent(line);
        }
    };
    this.$id = "ace/mode/yeswiki";
}).call(Mode.prototype);

exports.Mode = Mode;
});                (function() {
                    ace.require(["ace/mode/yeswiki"], function(m) {
                        if (typeof module == "object" && typeof exports == "object" && module) {
                            module.exports = m;
                        }
                    });
                })();
