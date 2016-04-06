/**
 * Created by jean on 4/6/2016.
 */
angular.module("sb-extraFields",[]).directive("wpImageImport",function(){
    return {
        "restrict": "E",
        "replace": true,
        "scope": {
            "desc_tip": "=",
            "description": "=",
            "label": "=",
            "fieldId": "=",
            "type": "=",
            "hasChanged": "=",
            "linkVal": "=",
            "imgId": "=",
            "changeFn": "&",
            "valueId": "="
        },
        link: function(scope){
            var init = false;
            scope.$watch("valueId",function(newVal,oldVal){
                if(init){
                    scope.internalChange = false;
                }
            });
            scope.$watch("internalChange",function(newVal,oldVal){
                if(init && scope.internalChange !== false){
                    scope.changeFn({id: scope.fieldId});
                } else {
                    init = true;
                }
            },true);
        },
        templateUrl:  wpAdminInfos.admin_includes + 'template_wp_image_import.html'
    }
});