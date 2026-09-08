/**
 * group-canvas-drawer.js
 *
 * Interactive HTML5 Drawing & Annotation Canvas Engine for Group Header Annotations
 * Features:
 * - In-place interactive text annotations with contrast outline and variable font sizes
 * - Freehand pen drawing with smooth spline curve interpolation
 * - Semi-transparent clinical highlighter
 * - Directional arrows with crisp arrowheads
 * - Geometric shapes: Rectangle / Box, Circle / Ellipse, Straight Line
 * - Non-destructive eraser tool (erases drawing strokes while keeping medical background diagram intact)
 * - Complete undo / redo history stack & clear canvas
 * - Vector JSON serialization & high-resolution PNG composite export
 * - Change event hooks for auto-saving and form binding
 * - Touch and mouse event support
 *
 * @package OpenEMR
 * @author  Nilesh Hake <nilesh.hake@nbhhealthsoft.com>
 */

(function (window) {
    'use strict';

    function GroupCanvasDrawer(containerId, options) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            throw new Error('Canvas container not found: ' + containerId);
        }

        this.options = Object.assign({
            width: 800,
            height: 600,
            backgroundImage: '',
            readOnly: false,
            onChange: null
        }, options || {});

        this.width = this.options.width;
        this.height = this.options.height;
        this.activeTool = 'pen'; // 'pen', 'highlighter', 'arrow', 'rect', 'circle', 'line', 'text', 'eraser'
        this.strokeColor = '#d9534f'; // Default red
        this.strokeWidth = 3;
        this.fontSize = 16;
        this.fontFamily = 'Arial, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';

        this.history = [];
        this.redoStack = [];
        this.isDrawing = false;
        this.startX = 0;
        this.startY = 0;
        this.currentStroke = null;
        this.bgImageObj = null;
        this.activeTextInput = null;

        this.initDOM();
        this.bindEvents();
        if (this.options.backgroundImage) {
            this.loadBackgroundImage(this.options.backgroundImage);
        }
    }

    GroupCanvasDrawer.prototype.initDOM = function () {
        this.container.innerHTML = '';
        this.container.style.position = 'relative';
        this.container.style.width = this.width + 'px';
        this.container.style.height = this.height + 'px';
        this.container.style.userSelect = 'none';
        this.container.style.webkitUserSelect = 'none';
        this.container.style.backgroundColor = '#ffffff';

        // 1. Background Canvas (renders medical diagram template)
        this.bgCanvas = document.createElement('canvas');
        this.bgCanvas.width = this.width;
        this.bgCanvas.height = this.height;
        this.bgCanvas.style.position = 'absolute';
        this.bgCanvas.style.left = '0';
        this.bgCanvas.style.top = '0';
        this.bgCanvas.style.zIndex = '1';
        this.bgCtx = this.bgCanvas.getContext('2d');
        this.container.appendChild(this.bgCanvas);

        // 2. Drawing Canvas (renders permanent vector strokes)
        this.drawCanvas = document.createElement('canvas');
        this.drawCanvas.width = this.width;
        this.drawCanvas.height = this.height;
        this.drawCanvas.style.position = 'absolute';
        this.drawCanvas.style.left = '0';
        this.drawCanvas.style.top = '0';
        this.drawCanvas.style.zIndex = '2';
        this.drawCtx = this.drawCanvas.getContext('2d');
        this.container.appendChild(this.drawCanvas);

        // 3. Temporary Canvas (renders live drag previews)
        this.tempCanvas = document.createElement('canvas');
        this.tempCanvas.width = this.width;
        this.tempCanvas.height = this.height;
        this.tempCanvas.style.position = 'absolute';
        this.tempCanvas.style.left = '0';
        this.tempCanvas.style.top = '0';
        this.tempCanvas.style.zIndex = '3';
        this.tempCanvas.style.cursor = 'crosshair';
        this.tempCtx = this.tempCanvas.getContext('2d');
        this.container.appendChild(this.tempCanvas);
    };

    GroupCanvasDrawer.prototype.loadBackgroundImage = function (src, callback) {
        if (!src) return;
        var self = this;
        var img = new Image();
        img.crossOrigin = 'Anonymous';
        img.onload = function () {
            self.bgImageObj = img;
            self.renderBackground();
            self.redrawAll();
            if (typeof callback === 'function') callback();
        };
        img.onerror = function () {
            console.warn('Group Canvas: Failed to load background image:', src);
        };
        img.src = src;
    };

    GroupCanvasDrawer.prototype.renderBackground = function () {
        this.bgCtx.clearRect(0, 0, this.width, this.height);
        if (!this.bgImageObj) return;

        var img = this.bgImageObj;
        var hRatio = this.width / img.width;
        var vRatio = this.height / img.height;
        var ratio = Math.min(hRatio, vRatio);

        var drawWidth = img.width * ratio;
        var drawHeight = img.height * ratio;
        var shiftX = (this.width - drawWidth) / 2;
        var shiftY = (this.height - drawHeight) / 2;

        this.bgCtx.drawImage(img, 0, 0, img.width, img.height, shiftX, shiftY, drawWidth, drawHeight);
    };

    GroupCanvasDrawer.prototype.getPos = function (e) {
        var rect = this.tempCanvas.getBoundingClientRect();
        var clientX = e.clientX;
        var clientY = e.clientY;

        if (e.touches && e.touches.length > 0) {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        }

        return {
            x: (clientX - rect.left) * (this.width / rect.width),
            y: (clientY - rect.top) * (this.height / rect.height)
        };
    };

    GroupCanvasDrawer.prototype.bindEvents = function () {
        var self = this;

        var onStart = function (e) {
            if (self.options.readOnly) return;
            if (self.activeTextInput) {
                self.commitTextInput();
                return;
            }

            e.preventDefault();
            self.isDrawing = true;
            var pos = self.getPos(e);
            self.startX = pos.x;
            self.startY = pos.y;

            if (self.activeTool === 'text') {
                self.createInPlaceTextInput(pos.x, pos.y);
                self.isDrawing = false;
                return;
            }

            if (self.activeTool === 'pen' || self.activeTool === 'highlighter' || self.activeTool === 'eraser') {
                self.currentStroke = {
                    type: self.activeTool,
                    color: self.strokeColor,
                    width: self.strokeWidth,
                    points: [pos]
                };
            }
        };

        var onMove = function (e) {
            if (!self.isDrawing || self.options.readOnly) return;
            e.preventDefault();
            var pos = self.getPos(e);

            if (self.activeTool === 'pen' || self.activeTool === 'highlighter' || self.activeTool === 'eraser') {
                if (self.currentStroke) {
                    self.currentStroke.points.push(pos);
                    self.drawLiveStroke();
                }
            } else if (['arrow', 'rect', 'circle', 'line'].indexOf(self.activeTool) !== -1) {
                self.drawLiveShape(pos);
            }
        };

        var onEnd = function (e) {
            if (!self.isDrawing) return;
            self.isDrawing = false;
            var pos = self.getPos(e);

            if (self.activeTool === 'pen' || self.activeTool === 'highlighter' || self.activeTool === 'eraser') {
                if (self.currentStroke && self.currentStroke.points.length > 0) {
                    self.addShapeToHistory(self.currentStroke);
                }
                self.tempCtx.clearRect(0, 0, self.width, self.height);
            } else if (['arrow', 'rect', 'circle', 'line'].indexOf(self.activeTool) !== -1) {
                var shape = {
                    type: self.activeTool,
                    color: self.strokeColor,
                    width: self.strokeWidth,
                    startX: self.startX,
                    startY: self.startY,
                    endX: pos.x,
                    endY: pos.y
                };
                self.addShapeToHistory(shape);
                self.tempCtx.clearRect(0, 0, self.width, self.height);
            }

            self.redrawAll();
        };

        this.tempCanvas.addEventListener('mousedown', onStart);
        this.tempCanvas.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onEnd);

        this.tempCanvas.addEventListener('touchstart', onStart, { passive: false });
        this.tempCanvas.addEventListener('touchmove', onMove, { passive: false });
        window.addEventListener('touchend', onEnd);
    };

    GroupCanvasDrawer.prototype.drawLiveStroke = function () {
        var pts = this.currentStroke.points;
        if (pts.length < 2) return;

        this.tempCtx.clearRect(0, 0, this.width, this.height);
        this.tempCtx.save();
        this.tempCtx.lineCap = 'round';
        this.tempCtx.lineJoin = 'round';
        this.tempCtx.lineWidth = this.currentStroke.width;

        if (this.currentStroke.type === 'highlighter') {
            this.tempCtx.strokeStyle = this.currentStroke.color;
            this.tempCtx.globalAlpha = 0.4;
            this.tempCtx.lineWidth = Math.max(12, this.currentStroke.width * 3);
        } else if (this.currentStroke.type === 'eraser') {
            this.tempCtx.strokeStyle = 'rgba(255, 255, 255, 0.9)';
            this.tempCtx.lineWidth = Math.max(16, this.currentStroke.width * 4);
        } else {
            this.tempCtx.strokeStyle = this.currentStroke.color;
            this.tempCtx.globalAlpha = 1.0;
        }

        this.renderSmoothStroke(this.tempCtx, pts);
        this.tempCtx.restore();
    };

    GroupCanvasDrawer.prototype.renderSmoothStroke = function (ctx, pts) {
        if (!pts || pts.length === 0) return;

        if (pts.length === 1) {
            ctx.beginPath();
            ctx.arc(pts[0].x, pts[0].y, Math.max(1, ctx.lineWidth / 2), 0, Math.PI * 2);
            ctx.fill();
            return;
        }

        ctx.beginPath();
        ctx.moveTo(pts[0].x, pts[0].y);

        if (pts.length === 2) {
            ctx.lineTo(pts[1].x, pts[1].y);
        } else {
            for (var i = 1; i < pts.length - 1; i++) {
                var xc = (pts[i].x + pts[i + 1].x) / 2;
                var yc = (pts[i].y + pts[i + 1].y) / 2;
                ctx.quadraticCurveTo(pts[i].x, pts[i].y, xc, yc);
            }
            ctx.lineTo(pts[pts.length - 1].x, pts[pts.length - 1].y);
        }
        ctx.stroke();
    };

    GroupCanvasDrawer.prototype.drawLiveShape = function (currentPos) {
        this.tempCtx.clearRect(0, 0, this.width, this.height);
        this.tempCtx.save();
        this.tempCtx.strokeStyle = this.strokeColor;
        this.tempCtx.lineWidth = this.strokeWidth;
        this.tempCtx.lineCap = 'round';
        this.tempCtx.lineJoin = 'round';

        this.renderShapeOnContext(this.tempCtx, {
            type: this.activeTool,
            color: this.strokeColor,
            width: this.strokeWidth,
            startX: this.startX,
            startY: this.startY,
            endX: currentPos.x,
            endY: currentPos.y
        });

        this.tempCtx.restore();
    };

    GroupCanvasDrawer.prototype.renderShapeOnContext = function (ctx, shape) {
        ctx.save();
        ctx.strokeStyle = shape.color;
        ctx.fillStyle = shape.color;
        ctx.lineWidth = shape.width;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        switch (shape.type) {
            case 'line':
                ctx.beginPath();
                ctx.moveTo(shape.startX, shape.startY);
                ctx.lineTo(shape.endX, shape.endY);
                ctx.stroke();
                break;

            case 'rect':
                ctx.beginPath();
                ctx.strokeRect(
                    Math.min(shape.startX, shape.endX),
                    Math.min(shape.startY, shape.endY),
                    Math.abs(shape.endX - shape.startX),
                    Math.abs(shape.endY - shape.startY)
                );
                break;

            case 'circle':
                var rx = Math.abs(shape.endX - shape.startX) / 2;
                var ry = Math.abs(shape.endY - shape.startY) / 2;
                var cx = Math.min(shape.startX, shape.endX) + rx;
                var cy = Math.min(shape.startY, shape.endY) + ry;
                ctx.beginPath();
                ctx.ellipse(cx, cy, Math.max(1, rx), Math.max(1, ry), 0, 0, 2 * Math.PI);
                ctx.stroke();
                break;

            case 'arrow':
                var headlen = Math.max(14, shape.width * 3.5);
                var dx = shape.endX - shape.startX;
                var dy = shape.endY - shape.startY;
                var angle = Math.atan2(dy, dx);
                
                // Draw shaft
                ctx.beginPath();
                ctx.moveTo(shape.startX, shape.startY);
                ctx.lineTo(shape.endX, shape.endY);
                ctx.stroke();

                // Draw filled arrowhead
                ctx.beginPath();
                ctx.moveTo(shape.endX, shape.endY);
                ctx.lineTo(
                    shape.endX - headlen * Math.cos(angle - Math.PI / 6),
                    shape.endY - headlen * Math.sin(angle - Math.PI / 6)
                );
                ctx.lineTo(
                    shape.endX - headlen * Math.cos(angle + Math.PI / 6),
                    shape.endY - headlen * Math.sin(angle + Math.PI / 6)
                );
                ctx.closePath();
                ctx.fill();
                break;

            case 'text':
                var fSize = shape.fontSize || 16;
                var fFam = shape.fontFamily || this.fontFamily;
                ctx.font = 'bold ' + fSize + 'px ' + fFam;
                ctx.textBaseline = 'middle';

                // Contrast outline for medical diagrams
                ctx.save();
                ctx.lineWidth = 3;
                ctx.strokeStyle = '#ffffff';
                ctx.strokeText(shape.text, shape.x, shape.y);
                ctx.restore();

                ctx.fillStyle = shape.color || '#d9534f';
                ctx.fillText(shape.text, shape.x, shape.y);
                break;

            case 'pen':
            case 'highlighter':
            case 'eraser':
                var pts = shape.points;
                if (pts && pts.length > 0) {
                    if (shape.type === 'highlighter') {
                        ctx.globalAlpha = 0.4;
                        ctx.lineWidth = Math.max(12, shape.width * 3);
                    } else if (shape.type === 'eraser') {
                        ctx.globalCompositeOperation = 'destination-out';
                        ctx.lineWidth = Math.max(16, shape.width * 4);
                    }
                    this.renderSmoothStroke(ctx, pts);
                }
                break;
        }

        ctx.restore();
    };

    GroupCanvasDrawer.prototype.createInPlaceTextInput = function (x, y) {
        var self = this;
        if (this.activeTextInput) {
            this.commitTextInput();
        }

        var inputWrapper = document.createElement('div');
        inputWrapper.className = 'oe-canvas-inplace-text-box';
        inputWrapper.style.position = 'absolute';
        inputWrapper.style.left = Math.min(x, self.width - 220) + 'px';
        inputWrapper.style.top = Math.min(y - 15, self.height - 70) + 'px';
        inputWrapper.style.zIndex = '100';

        var input = document.createElement('input');
        input.type = 'text';
        input.placeholder = 'Type annotation text & press Enter...';
        input.className = 'oe-canvas-text-input';
        input.style.fontSize = this.fontSize + 'px';
        input.style.color = this.strokeColor;

        var btnAdd = document.createElement('button');
        btnAdd.type = 'button';
        btnAdd.innerHTML = '<i class="fa fa-check"></i>';
        btnAdd.className = 'btn btn-primary btn-sm oe-canvas-text-submit-btn';

        var btnCancel = document.createElement('button');
        btnCancel.type = 'button';
        btnCancel.innerHTML = '&times;';
        btnCancel.className = 'btn btn-light btn-sm oe-canvas-text-cancel-btn';

        inputWrapper.appendChild(input);
        inputWrapper.appendChild(btnAdd);
        inputWrapper.appendChild(btnCancel);
        this.container.appendChild(inputWrapper);

        this.activeTextInput = {
            wrapper: inputWrapper,
            input: input,
            x: x,
            y: y
        };

        setTimeout(function () {
            input.focus();
        }, 50);

        var submitAction = function () {
            self.commitTextInput();
        };

        var cancelAction = function () {
            self.cancelTextInput();
        };

        btnAdd.addEventListener('click', submitAction);
        btnCancel.addEventListener('click', cancelAction);

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitAction();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                cancelAction();
            }
        });
    };

    GroupCanvasDrawer.prototype.commitTextInput = function () {
        if (!this.activeTextInput) return;
        var info = this.activeTextInput;
        var text = (info.input.value || '').trim();

        if (text) {
            var shape = {
                type: 'text',
                text: text,
                x: info.x,
                y: info.y,
                color: this.strokeColor,
                fontSize: this.fontSize,
                fontFamily: this.fontFamily
            };
            this.addShapeToHistory(shape);
            this.redrawAll();
        }

        this.cancelTextInput();
    };

    GroupCanvasDrawer.prototype.cancelTextInput = function () {
        if (this.activeTextInput && this.activeTextInput.wrapper) {
            if (this.activeTextInput.wrapper.parentNode) {
                this.activeTextInput.wrapper.parentNode.removeChild(this.activeTextInput.wrapper);
            }
            this.activeTextInput = null;
        }
    };

    GroupCanvasDrawer.prototype.addShapeToHistory = function (shape) {
        this.history.push(shape);
        this.redoStack = []; // clear redo on new action
        this.notifyChange();
    };

    GroupCanvasDrawer.prototype.redrawAll = function () {
        this.drawCtx.clearRect(0, 0, this.width, this.height);
        for (var i = 0; i < this.history.length; i++) {
            this.renderShapeOnContext(this.drawCtx, this.history[i]);
        }
    };

    GroupCanvasDrawer.prototype.undo = function () {
        if (this.history.length > 0) {
            this.redoStack.push(this.history.pop());
            this.redrawAll();
            this.notifyChange();
        }
    };

    GroupCanvasDrawer.prototype.redo = function () {
        if (this.redoStack.length > 0) {
            this.history.push(this.redoStack.pop());
            this.redrawAll();
            this.notifyChange();
        }
    };

    GroupCanvasDrawer.prototype.clear = function () {
        if (this.history.length === 0) return;
        if (confirm('Are you sure you want to clear all annotations from this diagram?')) {
            this.history = [];
            this.redoStack = [];
            this.redrawAll();
            this.notifyChange();
        }
    };

    GroupCanvasDrawer.prototype.notifyChange = function () {
        if (typeof this.options.onChange === 'function') {
            this.options.onChange(this);
        }
    };

    GroupCanvasDrawer.prototype.setTool = function (tool) {
        this.activeTool = tool;
        if (tool === 'text') {
            this.tempCanvas.style.cursor = 'text';
        } else if (tool === 'eraser') {
            this.tempCanvas.style.cursor = 'cell';
        } else {
            this.tempCanvas.style.cursor = 'crosshair';
        }
    };

    GroupCanvasDrawer.prototype.setColor = function (color) {
        this.strokeColor = color;
        if (this.activeTextInput && this.activeTextInput.input) {
            this.activeTextInput.input.style.color = color;
        }
    };

    GroupCanvasDrawer.prototype.setStrokeWidth = function (w) {
        this.strokeWidth = parseInt(w, 10) || 3;
    };

    GroupCanvasDrawer.prototype.setFontSize = function (size) {
        this.fontSize = parseInt(size, 10) || 16;
        if (this.activeTextInput && this.activeTextInput.input) {
            this.activeTextInput.input.style.fontSize = this.fontSize + 'px';
        }
    };

    GroupCanvasDrawer.prototype.getJSON = function () {
        return JSON.stringify(this.history);
    };

    GroupCanvasDrawer.prototype.loadJSON = function (jsonStr) {
        if (!jsonStr) return;
        try {
            var data = jsonStr;
            if (typeof data === 'string') {
                try {
                    data = JSON.parse(data);
                } catch (e1) {
                    console.warn('Initial JSON parse warning:', e1);
                }
            }
            if (typeof data === 'string') {
                try {
                    data = JSON.parse(data);
                } catch (e2) {}
            }
            if (data && typeof data === 'object' && !Array.isArray(data)) {
                if (Array.isArray(data.strokes)) {
                    data = data.strokes;
                } else if (Array.isArray(data.history)) {
                    data = data.history;
                } else {
                    var arr = [];
                    Object.keys(data).forEach(function (k) {
                        if (!isNaN(parseInt(k, 10)) && data[k] && typeof data[k] === 'object') {
                            arr.push(data[k]);
                        }
                    });
                    if (arr.length > 0) {
                        data = arr;
                    }
                }
            }
            if (Array.isArray(data)) {
                this.history = data;
                this.redoStack = [];
                this.redrawAll();
                this.notifyChange();
            }
        } catch (e) {
            console.error('Failed to parse canvas drawing JSON:', e);
        }
    };

    GroupCanvasDrawer.prototype.loadDrawingImage = function (pngDataUrl, callback) {
        if (!pngDataUrl) return;
        var self = this;
        var img = new Image();
        img.crossOrigin = 'Anonymous';
        img.onload = function () {
            self.drawCtx.clearRect(0, 0, self.width, self.height);
            self.drawCtx.drawImage(img, 0, 0, self.width, self.height);
            if (typeof callback === 'function') callback();
        };
        img.src = pngDataUrl;
    };

    GroupCanvasDrawer.prototype.getPNG = function () {
        // Render combined background + drawing into high-res export canvas
        var exportCanvas = document.createElement('canvas');
        exportCanvas.width = this.width;
        exportCanvas.height = this.height;
        var expCtx = exportCanvas.getContext('2d');

        // 1. Draw solid white background base
        expCtx.fillStyle = '#ffffff';
        expCtx.fillRect(0, 0, this.width, this.height);

        // 2. Draw background diagram image
        try {
            if (this.bgCanvas) {
                expCtx.drawImage(this.bgCanvas, 0, 0);
            }
        } catch (e) {
            console.warn('Group Canvas: bgCanvas export note:', e);
        }

        // 3. Draw saved annotations
        try {
            if (this.drawCanvas) {
                expCtx.drawImage(this.drawCanvas, 0, 0);
            }
        } catch (e) {
            console.warn('Group Canvas: drawCanvas export note:', e);
        }

        try {
            return exportCanvas.toDataURL('image/png');
        } catch (e) {
            try {
                return this.drawCanvas.toDataURL('image/png');
            } catch (e2) {
                return '';
            }
        }
    };

    window.GroupCanvasDrawer = GroupCanvasDrawer;

})(window);
