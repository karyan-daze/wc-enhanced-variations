/**
 * Created by jean on 3/16/2016.
 */

angular.module("var_bulk_edit", ['isteven-multi-select','sb-extraFields'])
.controller("varBulkCtrl", function($scope,$http){
    $scope.inProgress = false;
    $scope.productList = JSON.parse(wpAdminInfos.productList);
    $scope.extraDataDef = JSON.parse(wpAdminInfos.extraDataDef);

    $scope.modifiedFields = {};
    _.each($scope.extraDataDef,function(val){
        $scope.modifiedFields[val.name] = {modified:false,value:""};
    });


    $scope.loadAttributes = function(data){
      $scope.selectedProductAttributes = _.map(data.attributes, function(value){
          return value;
      });
    };

    $scope.loadModifiedFields = function(){
        var refVal = getReferencedValue();
        _.each($scope.modifiedFields,function(value,key){
            if(refVal.extraFieldsValue[key]){
                $scope.modifiedFields[key] = refVal.extraFieldsValue[key];
            } else {
                $scope.modifiedFields[key] = {};
            }
            $scope.modifiedFields[key]["modified"] = false;
        });
    };

    $scope.modifiedFieldChange = function(id){
        $scope.modifiedFields[id].modified = true;
    };

    function getReferencedValue(){
        var concernedObject = $scope.productList.filter(function(i){
            return i.id == $scope.selectedProduct[0].id;
        })[0];
        var concernedAttribute = _.filter(concernedObject.attributes,function(val,k){
            return k == $scope.selectedAttribute[0].name;
        })[0];
        var concernedValue = concernedAttribute.values.filter(function(val){
            return val.term_id == $scope.selectedAttributeValue[0].term_id;
        })[0];
        return concernedValue;
    }

    $scope.submitRequest = function(){
        var reallyModifiedValues = {};
        _.each($scope.modifiedFields,function(val,key){
            if(val.modified){
                reallyModifiedValues[key] = val.value;
            }
        });
        var request = {product:$scope.selectedProduct, attribute:$scope.selectedAttribute, value: $scope.selectedAttributeValue, modifiedValues: reallyModifiedValues};
        $scope.inProgress = true;
        $http({
            method: 'POST',
            url: ajaxurl,
            params: {action:"update_var_bulk_edit"},
            data: request,
        }).success(function(data, status, headers, config) {
            var refVal = getReferencedValue();
            var newExtraFieldsValue = data;
            angular.forEach(newExtraFieldsValue, function(val,key){
                refVal.extraFieldsValue[key] = $scope.modifiedFields[key];
                refVal.extraFieldsValue[key].value = val;
                $scope.modifiedFields[key].modified = false;
            });
            $scope.inProgress = false;
        });
    }
});