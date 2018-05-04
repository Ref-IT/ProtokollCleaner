/**
 * CodeMirror plugin for DokuWiki
 *
 * @author Albert Gasset <albertgasset@fsfe.org>
 * @license GNU GPL version 2 or later
 * @license https://choosealicense.com/licenses/gpl-2.0/
 */

/* global CodeMirror */

CodeMirror.defineMode('doku', function(config, parserConfig) {
    'use strict';

    var dokuModes;
    var mode = {

        blankLine: function(state) {
            if (state.current.patterns) {
                // Search for exit patterns of empty strings and start of line
                for (var i = 0; i < state.current.patterns.length; i += 1) {
                    var p = state.current.patterns[i];
                    if (p.sol && !p.match && p.exit) {
                        state.exit = true;
                        return;
                    }
                }
            }
            if (state.innerMode && state.innerMode.blankLine) {
                return state.innerMode.blankLine(state.innerState);
            }
        },

        copyState: function(state) {
            return {
                current: state.current,
                exit: state.exit,
                codeFilename: state.codeFilename,
                codeLang: state.codeLang,
                innerMode: state.innerMode,
                innerState: state.innerState ?
                    CodeMirror.copyState(state.innerMode, state.innerState) :
                    null,
                linkParam: state.linkParam,
                linkTitle: state.linkTitle,
                stack: state.stack.slice(0),
            };
        },

        indent: function(state, textAfter) {
            if (state.innerMode && state.innerMode.indent) {
                return state.innerMode.indent(state.innerState, textAfter);
            }
        },

        innerMode: function(state) {
            return {
                mode: state.innerMode || mode,
                state: state.innerMode ? state.innerState : state,
            };
        },

        startState: function() {
            return {
                codeFilename: false,
                codeLang: null,
                current: dokuModes[0],
                exit: false,
                innerMode: null,
                innerState: null,
                linkParam: null,
                linkTitle: false,
                stack: [],
            };
        },

        token: function (stream, state) {
            var style;

            if (state.exit) {
                // Previous match was an exit pattern
                exitInnerMode(state);
                state.current = state.stack.pop();
                state.exit = false;
            }

            style = dokuToken(stream, state);

            if (!stream.current() && !state.exit) {
                // No pattern matched
                if (state.innerMode) {
                    style = state.innerMode.token(stream, state.innerState);
                } else {
                    stream.next();
                }
            }

            return style;
        },
    };

    dokuModes = [{
        name: 'base', // 0
        allowedTypes: ['container', 'baseonly', 'formatting',
                       'substition', 'protected', 'disabled'],
    }, {
        name: 'listblock', // 10
        type: 'container',
        allowedTypes: ['formatting', 'substition', 'disabled', 'protected'],
        entries: [
            {sol: true, match: /^ {2,}[\-\*]/, style: 'def'},
            {sol: true, match: /^\t{1,}[\-\*]/, style: 'def'},
        ],
        patterns: [
            {sol: true, match: /^ {2,}[\-\*]/, style: 'def'},
            {sol: true, match: /^\t{1,}[\-\*]/, style: 'def'},
            {sol: true, exit: true},
        ],
    }, {
        name: 'preformatted', // 20
        type: 'protected',
        entries: [
            {sol: true, match: /^  (?![\*\-])/},
            {sol: true, match: /^\t(?![\*\-])/},
        ],
        patterns: [
            {sol: true, match: '  '},
            {sol: true, match: '\t'},
            {sol: true, exit: true},
        ],
        style: 'string',
    }, {
        name: 'notoc', // 30
        type: 'substition',
        entries: [{match: '~~NOTOC~~', exit: true}],
        style: 'meta',
    }, {
        name: 'nocache', // 40
        type: 'substition',
        entries: [{match: '~~NOCACHE~~', exit: true}],
        style: 'meta',
    }, {
        name: 'header', // 50
        type: 'baseonly',
        entries: [{match: /^[ \t]*={2}.+={2,}[ \t]*$/, exit: true}],
        style: 'header',
    }, {
        name: 'table', // 60
        type: 'container',
        allowedTypes: ['formatting', 'substition', 'disabled', 'protected'],
        entries: [
            {sol: true, match: '^', style: 'def'},
            {sol: true, match: '|', style: 'def'},
        ],
        patterns: [
            {match: '^', style: 'def'},
            {match: '|', style: 'def'},
            {match: /^[\t ]*:::[\t ]*(?=[\|\^])/, style: 'def'},
            {match: /^[\t ]+/},
            {sol: true, exit: true},
        ],
    }, {
        name: 'strong', // 70
        type: 'formatting',
        allowedTypes: ['formatting', 'substition', 'disabled'],
        entries: [{match: '**'}],
        patterns: [{match: '**', exit: true}],
        style: 'strong',
    }, {
        name: 'emphasis', // 80
        type: 'formatting',
        allowedTypes: ['formatting', 'substition', 'disabled'],
        entries: [{match: /^\/\/(?=[^\x00]*[^:])/}],
        patterns: [{match: '//', exit: true}],
        style: 'em',
    }, {
        name: 'underline', // 90
        type: 'formatting',
        allowedTypes: ['formatting', 'substition', 'disabled'],
        entries: [{match: '__'}],
        patterns: [{match: '__', exit: true}],
        style: 'underline',
    }, {
        name: 'monospace', // 100
        type: 'formatting',
        allowedTypes: ['formatting', 'substition', 'disabled'],
        entries: [{match: '\'\''}],
        patterns: [{match: '\'\'', exit: true}],
        style: 'quote',
    }, {
        name: 'subscript', // 110
        type: 'formatting',
        allowedTypes: ['formatting', 'substition', 'disabled'],
        entries: [{match: '<sub>', style: 'tag'}],
        patterns: [{match: '</sub>', exit: true, style: 'tag'}],
    }, {
        name: 'superscript', // 120
        type: 'formatting',
        allowedTypes: ['formatting', 'substition', 'disabled'],
        entries: [{match: '<sup>', style: 'tag'}],
        patterns: [{match: '</sup>',  exit: true, style: 'tag'}],
    }, {
        name: 'deleted', // 130
        type: 'formatting',
        allowedTypes: ['formatting', 'substition', 'disabled'],
        entries: [{match: '<del>', style: 'tag'}],
        patterns: [{match: '</del>', exit: true, style: 'tag'}],
    }, {
        name: 'linebreak', // 140
        type: 'substition',
        entries: [{match: /^\\\\(?:[ \t]|$)/, exit: true}],
        style: 'tag',
    }, {
        name: 'footnote', // 150
        type: 'formatting',
        allowedTypes: ['container', 'formatting', 'substition',
                       'protected', 'disabled'],
        entries: [{match: '((', style: 'tag'}],
        patterns: [{match: '))', exit: true, style: 'tag'}],
    }, {
        name: 'hr', // 160
        type: 'container',
        entries: [{sol: true, match: /^[ \t]*-{4,}[ \t]*$/, exit: true}],
        style: 'hr',
    }, {
        name: 'unformatted', // 170
        type: 'disabled',
        entries: [{match: '<nowiki>', style: 'tag'}],
        patterns: [{match: '</nowiki>', exit: true, style: 'tag'}],
    }, {
        name: 'unformattedalt', // 170
        type: 'disabled',
        entries: [{match: '%%'}],
        patterns: [{match: '%%', exit: true}],
        style: 'string',
    }, {
        name: 'php', // 180
        type: 'protected',
        entries: [{match: '<php>', style: 'tag', lang: 'php'}],
        patterns: [{match: '</php>', exit: true, style: 'tag'}],
    }, {
        name: 'phpblock', // 180
        type: 'protected',
        entries: [{match: '<PHP>', style: 'tag', lang: 'php'}],
        patterns: [{match: '</PHP>', exit: true, style: 'tag'}],
    }, {
        name: 'html', // 190
        type: 'protected',
        entries: [{match: '<html>', style: 'tag', lang: 'html'}],
        patterns: [{match: '</html>', exit: true, style: 'tag'}],
    }, {
        name: 'htmlblock', // 190
        type: 'protected',
        entries: [{match: '<HTML>', style: 'tag', lang: 'html'}],
        patterns: [{match: '</HTML>', exit: true, style: 'tag'}],
    }, {
        name: 'code', // 200
        type: 'protected',
        entries: [{match: /^<code(?=\s|>|$)/, style: 'tag'}],
        patterns: [{match: '</code>', exit: true, style: 'tag'}],
        token: codeToken,
    }, {
        name: 'file', // 210
        type: 'protected',
        entries: [{match: /^<file(?=\s|>|$)/, style: 'tag'}],
        patterns: [{match: '</file>', exit: true, style: 'tag'}],
        token: codeToken,
    }, {
        name: 'quote', // 220,
        type: 'container',
        allowedTypess: ['formatting', 'substition', 'disabled', 'protected'],
        entries: [{sol: true, match: /^>{1,}/, style: 'def'}],
        patterns: [
            {sol: true, match: /^>{1,}/, style: 'def'},
            {sol: true, exit: true},
        ],
    }];
/*
   if (parserConfig.smileys.length > 0) {
        dokuModes.push({
            name: 'smiley', // 230
            type: 'substition',
            entries: [{
                behind: /\B$/,
                match: wordsRegExp(parserConfig.smileys, '(?=\\W|$)'),
                exit: true,
            }],
            style: 'keyword',
        });
    }*//*
    if (parserConfig.acronyms.length > 0) {
        dokuModes.push({
            name: 'acronym', // 240
            type: 'substition',
            entries: [{
                behind: /\B$/,
                match: wordsRegExp(parserConfig.acronyms, '(?=\\W|$)'),
                exit: true,
            }],
            style: 'keyword',
        });
    }*//*
    if (parserConfig.entities.length > 0) {
        dokuModes.push({
            name: 'entity', // 260
            type: 'substition',
            entries: [{match: wordsRegExp(parserConfig.entities), exit: true}],
            style: 'keyword',
        });
    }*/

    dokuModes.push({
        name: 'multipluentity', // 270
        type: 'substition',
        sort: 270,
        entries: [{behind: /\B$/, match: /^(?:[1-9]|\d{2,})(?=[xX]\d+\b)/}],
        patterns: [
            {match: /^[xX]/, style: 'keyword'},
            {match: /^\d+\b/, exit: true},
        ],
    });

    if (parserConfig.camelcase) {
        dokuModes.push({
            name: 'camelcaselink', // 290
            type: 'substition',
            emtry: [{
                behind: /\B$/,
                match: /^[A-Z]+[a-z]+[A-Z][A-Za-z]*\b/,
                exit: true,
            }],
            style: 'link',
        });
    }

    dokuModes.push({
        name: 'internallink', // 300
        type: 'substition',
        entries: [{match: '[['}],
        token: function(stream, state) {
            var style;
            if (stream.match(']]')) {
                state.current = state.stack.pop();
                state.linkTitle = false;
            } else if (!state.linkTitle && stream.match('|')) {
                state.linkTitle = true;
            } else {
                stream.next();
                style = state.linkTitle ? 'string' : 'link';
            }
            return tokenStyles(state, style);
        },
    }, {
        name: 'rss', // 310
        type: 'substition',
        entries: [{match: '{{rss>', style: 'tag'}],
        patterns: [{match: '}}', exit: true, style: 'tag'}],
    }, {
        name: 'media', // 320
        type: 'substition',
        entries: [{match: /^\{\{ */}],
        token: function(stream, state) {
            var style;
            if (stream.match(/^ *\}\}/)) {
                state.current = state.stack.pop();
                state.linkParam = false;
                state.linkTitle = false;
            } else if (state.linkTitle) {
                style = 'string';
                stream.next();
            } else if (stream.match(/^\s*\|/)) {
                state.linkTitle = true;
            } else if (state.linkParam) {
                if (stream.match(/^(?:nolink|direct|linkonly)/)) {
                    style = 'keyword';
                } else if (stream.match(/^(?:nocache|recache)/)) {
                    style = 'meta';
                } else if (stream.match(/^\d+(?:[xX]\d+)?/)) {
                    style = 'number';
                } else if (!stream.match(/^\s+/)) {
                    stream.next();
                    style = 'error';
                }
            } else if (stream.match(/^\?(?=[^\?]*$)/)) {
                state.linkParam = true;
            } else {
                stream.next();
                style = 'link';
            }
            return tokenStyles(state, style);
        },
    }, {
        name: 'externallink', // 330
        type: 'substition',
        entries: [{
            behind: /\B$/,
            match: externalLinkRegExp(parserConfig.schemes),
            exit: true,
        }],
        style: 'link',
    }, {
        name: 'emaillink', // 340
        type: 'substition',
        entries: [{match: emailLinkRegExp(), exit: true}],
        style: 'link',
    }, {
        name: 'windowssharelink', // 350
        type: 'substition',
        entries: [{match: /^\\\\\w+?(?:\\[\w-$]+)+/, exit: true}],
        style: 'link',
    }, {
        name: 'filelink', // 360
        type: 'substition',
        entries: [{behind: /\B$/, match: fileLinkRegExp(), exit: true}],
        style: 'link',
    });

    connectDokuModes();

    return mode;


    function escapeRegExp(string) {
        return string.replace(/([.*+?^=!:${}()|\[\]\/\\])/g, '\\$1');
    }

    function wordsRegExp(words, end, flags) {
        var escapedWords = [];
        for (var i = 0; i < words.length; i += 1) {
            escapedWords.push(escapeRegExp(words[i]));
        }
        end = end || '';
        flags = flags || '';
        return new RegExp('^(?:' + escapedWords.join('|') + ')' + end, flags);
    }

    function emailLinkRegExp() {
        var text = '[0-9a-zA-Z!#$%&\'*+/=?^_`{|}~-]+';
        var email = (text + '(?:\\.' + text + ')*@' +
                     '(?:[0-9a-z][0-9a-z-]*\\.)+(?:[a-z]{2,4}|museum|travel)');
        return new RegExp('^<' + email + '>', 'i');
    }
    
    function externalLinkRegExp(schemes) {
        var punc = '.:?\\-;,';
        var host = '\\w' + punc;
        var any = '\\w/\\#~:.?+=&%@!\\-\\[\\]' + punc;
        var patterns = [];
        if (typeof(schemes)!='undefined'){
	        for (var i = 0; i < schemes.length; i += 1) {
	            patterns.push(schemes[i] + '://[' + any + ']+?(?=[' +
	                          punc + ']*[^' + any + ']|$)');
	        }
        }
        patterns.push('www?\\.[' + host + ']+?\\.[' + host + ']+?[' +
                      any + ']+?(?=[' + punc + ']*[^' + any + ']|$)');
        patterns.push('ftp?\\.[' + host + ']+?\\.[' + host + ']+?[' +
                      any + ']+?(?=[' + punc + ']*[^' + any + ']|$)');
        return new RegExp('^(?:' + patterns.join('|') + ')', 'i');
    }

    function fileLinkRegExp() {
        var punc = '.:?\\-;,';
        var any = '\\w/\\#~:.?+=&%@!\\-\\[\\]' + punc;
        return new RegExp('^file://[' + any + ']+?(?=[' + punc + ']*[^' +
                          any + ']|$)', 'i');
    }

    function enterInnerMode(state, lang) {
        state.innerMode = parserConfig.loadMode(lang);
        if (state.innerMode.startState) {
            state.innerState = state.innerMode.startState();
        }
        state.blockCommentStart = state.innerMode.blockCommentStart;
        state.blockCommentEnd = state.innerMode.blockCommentEnd;
        state.lineComment = state.innerMode.lineComment;
        state.electricChars = state.innerMode.electricChars;
        state.electricInput = state.innerMode.electricInput;
    }

    function exitInnerMode(state) {
        state.innerMode = null;
        state.innerState = null;
        state.blockCommentStart = null;
        state.blockCommentEnd = null;
        state.lineComment = null;
        state.electricChars = null;
        state.electricInput = null;
    }

    function matchPatterns(stream, state, patterns) {
        if (!patterns) {
            return null;
        }

        var behind = stream.string.slice(stream.lineStart, stream.pos);

        for (var i = 0; i < patterns.length; i += 1) {
            var p = patterns[i];
            if (p.sol && !stream.sol()) {
                continue;
            }
            if (p.behind && !p.behind.test(behind)) {
                continue;
            }
            if (p.match && !stream.match(p.match)) {
                continue;
            }
            return patterns[i];
        }

        return null;
    }

    function codeToken(stream, state) {
        // Token function that parses code/file parameters

        if (state.innerMode) {
            return;
        }

        if (stream.match('>')) {
            enterInnerMode(state, state.codeLang);
            state.codeLang = null;
            state.codeFilename = false;
            return tokenStyles(state, 'tag');
        }

        if (stream.match(/^\s+/)) {
            return tokenStyles(state);
        }

        if (stream.match(/^[^\s>]+/)) {
            var style;
            if (!state.codeLang) {
                state.codeLang  = stream.current();
                if (parserConfig.validLang(state.codeLang)) {
                    style = 'keyword';
                } else {
                    style = 'error';
                }
            } else if (!state.codeFilename) {
                state.codeFilename = true;
                style = 'string';
            } else {
                style = 'error';
            }
            return tokenStyles(state, style);
        }
    }

    function dokuToken(stream, state) {
        var allowed = state.current.allowedModes;
        var pattern, style;

        // Match patterns
        for (var i = 0; !pattern && i < allowed.length; i += 1) {
            if (allowed[i] === state.current) {
                // Try custom function first
                if (state.current.token) {
                    style = state.current.token(stream, state);
                    if (stream.current()) {
                        return style;
                    }
                }
                pattern = matchPatterns(stream, state, allowed[i].patterns);
            } else {
                pattern = matchPatterns(stream, state, allowed[i].entries);
                if (pattern) {
                    state.stack.push(state.current);
                    state.current = allowed[i];
                    if (pattern.lang) {
                        enterInnerMode(state, pattern.lang);
                    }
                }
            }
        }

        if (pattern) {
            if (pattern.exit) {
                state.exit = true;
            }
            return tokenStyles(state, pattern.style);
        } else {
            return tokenStyles(state);
        }
    }

    function tokenStyles(state, style) {
        var styles = [];

        for (var i = 0; i < state.stack.length; i += 1) {
            if (state.stack[i].style) {
                styles.push(state.stack[i].style);
            }
        }

        if (state.current.style) {
            styles.push(state.current.style);
        }

        if (style) {
            styles.push(style);
        }

        return styles.join(' ') || null;
    }

    function connectDokuModes() {
        for (var i = 0; i < dokuModes.length; i += 1) {
            var src = dokuModes[i];
            src.allowedModes = [];
            if (src.allowedTypes) {
                connectMode(src);
            } else {
                src.allowedModes.push(src);
            }
        }

        function connectMode(src) {
            for (var i = 0; i < dokuModes.length; i += 1) {
                var dest = dokuModes[i];
                if (src === dest || allowsType(src, dest.type)) {
                    src.allowedModes.push(dest);
                }
            }
        }

        function allowsType(mode, type) {
            for (var i = 0; i < mode.allowedTypes.length; i += 1) {
                if (mode.allowedTypes[i] === type) {
                    return true;
                }
            }
            return false;
        }
    }

});