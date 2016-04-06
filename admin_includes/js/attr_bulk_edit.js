/**
 * Created by jean on 3/16/2016.
 */

angular.module("attr_bulk_edit", ['isteven-multi-select','sb-extraFields'])
.controller("attrBulkCtrl", function($scope,$http){
    $scope.inProgress = false;
    $scope.attributesList = JSON.parse(wpAdminInfos.attributesList);
    $scope.extraDataDef = JSON.parse(wpAdminInfos.extraDataDef);
    $scope.modifiedFields = {};
    _.each($scope.extraDataDef,function(val){
        $scope.modifiedFields[val.name] = {modified:false,value:""};
    });

    $scope.loadModifiedFields = function(){
        var refVal = getRefVal();
        _.each($scope.modifiedFields,function(value,key){
            if(refVal.extraFieldsValue[key]){
                $scope.modifiedFields[key] = refVal.extraFieldsValue[key];
            } else {
                $scope.modifiedFields[key] = {};
            }
            $scope.modifiedFields[key]["modified"] = false;
        });
    };

    function getRefVal(){
        return $scope.attributesList.filter(function(val){
            return $scope.selectedAttribute[0].name == val.name;
        })[0];
    }

    $scope.modifiedFieldChange = function(id){
        $scope.modifiedFields[id].modified = true;
    };

    $scope.submitRequest = function(){
        var reallyModifiedValues = {};
        _.each($scope.modifiedFields,function(val,key){
            if(val.modified){
                reallyModifiedValues[key] = val.value;
            }
        });
        var request = {attribute:$scope.selectedAttribute, modifiedValues: reallyModifiedValues};
        $scope.inProgress = true;
        $http({
            method: 'POST',
            url: ajaxurl,
            params: {action:"update_attr_bulk_edit"},
            data: request,
        }).success(function(data, status, headers, config) {
            var newExtraFieldsValue = data;
            var refVal = getRefVal();
            angular.forEach(newExtraFieldsValue, function(val,key){
                refVal.extraFieldsValue[key] = $scope.modifiedFields[key];
                refVal.extraFieldsValue[key].value = val;
                $scope.modifiedFields[key].modified = false;
            });
            $scope.inProgress = false;
        });
    }
});