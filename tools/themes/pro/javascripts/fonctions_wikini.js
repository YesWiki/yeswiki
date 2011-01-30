function fKeyDown() {
    if (event.keyCode == 9) {
        event.returnValue= false;
        document.selection.createRange().text = String.fromCharCode(9);
    }
}
