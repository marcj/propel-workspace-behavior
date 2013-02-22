propel-workspace-behaviour
==========================

A behaviour for propel to store entries under different workspaces.

For Propel `<2.0`.
This is in early development.


Usage
-----

```xml
<database>

    <table name="...">
       [...]
       <behavior name="workspace"/>
       [...]
    </table>

    <!-- TODO -->
    <behavior name="workspace">
         <parameter name="workspace_getter" value="myCallable::method" />
    </behavior>
    <!--- /TODO -->

</database>
```