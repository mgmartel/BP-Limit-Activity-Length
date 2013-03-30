;(function($){ // Closure

  $.fn.clearTextLimit = function() {
      return this.each(function() {
         this.onkeydown = this.onkeyup = null;
      });
  };
  $.fn.textLimit = function( limit , callback ) {
      if ( typeof callback !== 'function' ) var callback = function() {};
      return this.each(function() {
        this.limit = limit;
        this.callback = callback;
        this.onkeydown = this.onkeyup = this.onfocus = function() {
          this.value = this.value.substr(0,this.limit);
          this.reached = this.limit - this.value.length;
          this.reached = ( this.reached == 0 ) ? true : false;
          return this.callback( this.value.length, this.limit, this.reached );
        }
      });
  };

  $(document).ready(function() {
    var activity_limit = BPLal.limit;

    $("#whats-new-submit").after("<div id='whats-new-limit'></div>");
    $('textarea#whats-new').textLimit(activity_limit,function( length, limit ){
      $("#whats-new-limit").text( limit - length );
    }).trigger("keyup");
  });

})(jQuery);